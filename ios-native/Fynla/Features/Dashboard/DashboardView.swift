import SwiftUI

// Transcribes /m's dashboard (resources/mobile/views/Dashboard.vue +
// dashboard.css): gradient hero carrying the level card and milestone nudge
// (continuing the shell header's shared gradient), the LEVEL UP callout with
// the focus carousel, Today's insight, the finances grid, and the milestone
// toast. The fixed hamburger + greeting app bar lives in the shell.
struct DashboardView: View {
    let model: DashboardModel
    let shareClient: any ShareContentClient
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String?) -> Void

    @State private var dismissedMilestoneIDs: Set<String> = []
    @State private var shareContent: ShareContent?

    var body: some View {
        content
            .background(FynlaColor.pageBackground)
            .task { await model.load() }
            .sheet(item: $shareContent) { content in
                ShareContentSheet(content: content)
            }
    }

    @ViewBuilder
    private var content: some View {
        switch model.state {
        case .idle, .loading:
            DashboardLoadingView()
        case let .loaded(snapshot):
            dashboard(snapshot)
        case let .offline(previous):
            if let previous {
                dashboard(previous, offline: true)
            } else {
                DashboardErrorView(
                    message: "You're offline. Reconnect to load your dashboard."
                ) {
                    Task { await model.load() }
                }
            }
        case .unauthenticated:
            DashboardErrorView(
                message: "Your secure session has expired. Please sign in again.",
                retry: nil
            )
        case let .failed(requestID):
            DashboardErrorView(message: failureMessage(requestID: requestID)) {
                Task { await model.load() }
            }
        }
    }

    private func dashboard(
        _ snapshot: DashboardSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                hero(snapshot)

                Group {
                    if offline {
                        Text("You're offline. Showing your last loaded dashboard.")
                            .font(.system(size: 13))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                            .padding(12)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .background(FynlaColor.Token.savannah100.color)
                            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                            .accessibilityIdentifier("dashboard.offline")
                    }

                    FocusAreasView(
                        areas: snapshot.focusAreas,
                        percentile: snapshot.percentile,
                        onAction: { action in
                            switch action.action.kind {
                            case .navigate:
                                onRoute(
                                    SemanticDestinationResolver.route(
                                        for: action.action.destination,
                                        legacyPath: action.action.payload
                                    )
                                )
                            case .fynCapture:
                                onOpenFyn(action.action.payload)
                            case .unknown:
                                // /m ignores unrecognised action kinds.
                                break
                            }
                        },
                        onComplete: { action in
                            Task { await model.complete(action) }
                        },
                        onViewAll: { key in
                            onRoute(
                                SemanticDestinationResolver.route(
                                    for: nil,
                                    legacyPath: key
                                )
                            )
                        },
                        onSeeAllActions: {
                            onRoute(.achievements)
                        },
                        completingActionIDs: model.completingActionIDs
                    )
                    // /m's md-callout margin-top clears the level card's
                    // downward overflow past the hero box — needed only when
                    // no milestone nudge already provides that clearance.
                    .padding(.top, snapshot.nextMilestone == nil ? 128 : 0)

                    if let actionMessage = model.actionMessage {
                        Text(actionMessage)
                            .font(.system(size: 13))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                            .accessibilityIdentifier("dashboard.action-error")
                    }

                    if let insight = snapshot.fynInsight, !insight.isEmpty {
                        insightCard(insight)
                    }

                    FinancePanelsView(
                        panels: FinancePanel.panels(from: snapshot),
                        onRoute: onRoute
                    )
                }
                .padding(.horizontal, 16)

                // md-bottom-pad: clears the docked Fyn bar.
                Color.clear.frame(height: 80)
            }
        }
        .refreshable { await model.refresh() }
        // Screen identifier on the scroll container — the pattern that keeps
        // sibling header buttons' identifiers resolvable.
        .accessibilityIdentifier("dashboard.screen")
        .overlay(alignment: .top) {
            if let milestone = activeMilestoneToast(snapshot) {
                milestoneToast(milestone)
            }
        }
    }

    // Gradient hero (md-scroll-hero): transcribes /m's box model — the level
    // card carries a -144 bottom margin so the hero box (and its gradient,
    // capped at --grad-span) ends well above the card's lower half. The
    // milestone nudge sits BELOW the card (a 152pt top margin clears the
    // card's overflow — the level hero must never be obscured, CSJ
    // 2026-07-20); the callout's clearance margin applies only when no nudge
    // is present.
    private func hero(_ snapshot: DashboardSnapshot) -> some View {
        VStack(spacing: 0) {
            LevelWheelCard(level: snapshot.level) {
                onRoute(.achievements)
            }
            .padding(.bottom, -144)

            if let milestone = snapshot.nextMilestone {
                NextMilestoneView(milestone: milestone) {
                    onRoute(route(forMilestone: milestone.route))
                }
                .padding(.top, 152)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .padding(.bottom, 24)
        .frame(maxWidth: .infinity)
        .background { MobileChromeGradientSlice() }
    }

    // Today's insight (md-insight): plain-text white card.
    private func insightCard(_ insight: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Today's insight")
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon600.color)
            Text(insight)
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
                .lineSpacing(3)
        }
        .padding(.horizontal, 18)
        .padding(.vertical, 16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .shadow(color: FynlaColor.Token.horizon500.color.opacity(0.06), radius: 5, y: 2)
        .accessibilityIdentifier("dashboard.insight")
    }

    private func activeMilestoneToast(_ snapshot: DashboardSnapshot) -> DashboardMilestone? {
        snapshot.newMilestones.first { !dismissedMilestoneIDs.contains($0.id) }
    }

    // Milestone celebration toast (md-milestone): spring gradient banner with
    // Share + Dismiss, floating below the hero.
    private func milestoneToast(_ milestone: DashboardMilestone) -> some View {
        HStack(alignment: .center, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text("MILESTONE REACHED")
                    .font(.system(size: 10, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(.white.opacity(0.85))
                Text(milestone.label)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(.white)
            }
            .frame(maxWidth: .infinity, alignment: .leading)

            VStack(alignment: .trailing, spacing: 6) {
                Button {
                    share(milestone)
                } label: {
                    Text("Share")
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.spring600.color)
                        .padding(.horizontal, 14)
                        .padding(.vertical, 6)
                        .background(Color.white)
                        .clipShape(Capsule())
                }
                Button("Dismiss") {
                    dismissedMilestoneIDs.insert(milestone.id)
                }
                .font(.system(size: 11))
                .foregroundStyle(.white.opacity(0.85))
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(
            LinearGradient(
                colors: [
                    FynlaColor.Token.spring500.color,
                    FynlaColor.Token.spring600.color,
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .shadow(color: FynlaColor.Token.spring500.color.opacity(0.32), radius: 12, y: 8)
        .padding(.horizontal, 16)
        // Floats BELOW the level card — the level hero must never be
        // obscured (CSJ 2026-07-20; mirrors /m's md-milestone top 25rem).
        .padding(.top, 308)
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("dashboard.milestone-toast")
    }

    // Share the crossed milestone via the server's share copy, mirroring /m's
    // shareMilestone -> doShare(share_type).
    private func share(_ milestone: DashboardMilestone) {
        Task { @MainActor in
            let content = (try? await shareClient.content(
                for: milestone.shareType ?? "app_referral"
            )) ?? ShareContent(
                title: "Fynla",
                text: "Milestone reached on Fynla — \(milestone.label)",
                url: "https://fynla.org"
            )
            shareContent = content
        }
    }

    private func failureMessage(requestID: String?) -> String {
        guard let requestID else {
            return "We could not load your dashboard. Please try again."
        }
        return "We could not load your dashboard. Reference: \(requestID)"
    }

    private func route(forMilestone route: String) -> AppRoute {
        switch route {
        case "m-net-worth": .netWorth(category: nil)
        case "m-goals": .goals
        case "m-savings": .savings(accountID: nil)
        case "m-retirement": .retirement(pensionType: nil, id: nil)
        case "m-protection": .protection(policyType: nil, id: nil)
        case "m-estate": .estate
        case "tax-strategy": .taxStrategy
        default: .dashboard
        }
    }
}

// Centred ring + £ coin loader transcribing /m's md-loader: 96pt ring with a
// raspberry top arc around a raspberry-gradient coin.
struct DashboardLoadingView: View {
    var message: String = "Loading your dashboard…"
    @State private var isSpinning = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        VStack(spacing: 20) {
            ZStack {
                Circle()
                    .stroke(FynlaColor.Token.horizon100.color, lineWidth: 6)
                Circle()
                    .trim(from: 0, to: 0.25)
                    .stroke(
                        FynlaColor.Token.raspberry500.color,
                        style: StrokeStyle(lineWidth: 6, lineCap: .round)
                    )
                    .rotationEffect(.degrees(isSpinning ? 360 : 0))
                    .animation(
                        reduceMotion
                            ? nil
                            : .linear(duration: 0.9).repeatForever(autoreverses: false),
                        value: isSpinning
                    )
                Text("£")
                    .font(.system(size: 24, weight: .black))
                    .foregroundStyle(.white)
                    .frame(width: 51, height: 51)
                    .background(
                        LinearGradient(
                            colors: [
                                FynlaColor.Token.raspberry400.color,
                                FynlaColor.Token.raspberry600.color,
                            ],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .clipShape(Circle())
                    .shadow(
                        color: FynlaColor.Token.raspberry500.color.opacity(0.35),
                        radius: 6,
                        y: 4
                    )
            }
            .frame(width: 96, height: 96)
            .accessibilityHidden(true)

            Text(message)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .onAppear { isSpinning = true }
        .accessibilityLabel(message)
    }
}

// Error state matching /m's md-loader error branch: message + raspberry
// "Try again" pill.
struct DashboardErrorView: View {
    let message: String
    var retry: (() -> Void)?

    init(message: String, retry: (() -> Void)? = nil) {
        self.message = message
        self.retry = retry
    }

    var body: some View {
        VStack(spacing: 16) {
            Text(message)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.center)

            if let retry {
                Button(action: retry) {
                    Text("Try again")
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 20)
                        .padding(.vertical, 8)
                        .background(FynlaColor.Token.raspberry500.color)
                        .clipShape(Capsule())
                }
                .accessibilityIdentifier("dashboard.retry")
            }
        }
        .padding(.horizontal, 24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
