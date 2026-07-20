import SwiftUI

// Transcribes /m's dashboard (resources/mobile/views/Dashboard.vue +
// dashboard.css): fixed gradient header with hamburger + greeting, gradient
// hero carrying the level card and milestone nudge, the LEVEL UP callout with
// the focus carousel, Today's insight, the finances grid, and the milestone
// toast. The shared 135° horizon→raspberry gradient spans header + hero
// (--grad-span 22rem = 352pt) so the two read as one at rest.
struct DashboardView: View {
    let model: DashboardModel
    let greetingName: () -> String?
    let shareClient: any ShareContentClient
    let onOpenMenu: () -> Void
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String?) -> Void

    @State private var dismissedMilestoneIDs: Set<String> = []
    @State private var shareContent: ShareContent?

    private let gradientSpan: CGFloat = 352
    private let headerContentHeight: CGFloat = 72

    private var sharedGradient: LinearGradient {
        LinearGradient(
            colors: [
                FynlaColor.Token.horizon500.color,
                FynlaColor.Token.raspberry500.color,
            ],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }

    var body: some View {
        GeometryReader { geo in
            let safeTop = geo.safeAreaInsets.top
            VStack(spacing: 0) {
                header(safeTop: safeTop)
                content(safeTop: safeTop)
            }
            .ignoresSafeArea(edges: .top)
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .sheet(item: $shareContent) { content in
            ShareContentSheet(content: content)
        }
    }

    // Fixed gradient app bar (md-header): translucent circular hamburger +
    // white greeting. Paints the top slice of the shared gradient.
    private func header(safeTop: CGFloat) -> some View {
        HStack(spacing: 12) {
            Button(action: onOpenMenu) {
                Image(systemName: "line.3.horizontal")
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(.white)
                    .frame(width: 44, height: 44)
                    .background(Color.white.opacity(0.18))
                    .clipShape(Circle())
            }
            .accessibilityLabel("Open menu")
            .accessibilityIdentifier("navigation.open")

            Text(greeting)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(.white)
                .lineLimit(1)
                .frame(maxWidth: .infinity, alignment: .leading)
                .accessibilityAddTraits(.isHeader)
                .accessibilityIdentifier("dashboard.greeting")

            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 16)
        .padding(.top, safeTop + 14)
        .padding(.bottom, 14)
        .background(alignment: .top) {
            // Clipped to the header visually, but the oversized rectangle
            // would still swallow taps over the hero below without
            // allowsHitTesting(false).
            Rectangle()
                .fill(sharedGradient)
                .frame(height: gradientSpan)
                .allowsHitTesting(false)
        }
        .clipped()
        .zIndex(10)
    }

    // Time-of-day greeting, mirroring /m's computed greeting.
    private var greeting: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let part = hour < 12 ? "morning" : (hour < 18 ? "afternoon" : "evening")
        if let name = greetingName(), !name.isEmpty {
            return "Good \(part), \(name)"
        }
        return "Good \(part), there"
    }

    @ViewBuilder
    private func content(safeTop: CGFloat) -> some View {
        switch model.state {
        case .idle, .loading:
            DashboardLoadingView()
        case let .loaded(snapshot):
            dashboard(snapshot, safeTop: safeTop)
        case let .offline(previous):
            if let previous {
                dashboard(previous, safeTop: safeTop, offline: true)
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
        safeTop: CGFloat,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                hero(snapshot, safeTop: safeTop)

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
                                onRoute(route(forPath: action.action.payload))
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
                            onRoute(route(forPath: key))
                        },
                        onSeeAllActions: {
                            onRoute(.achievements)
                        },
                        completingActionIDs: model.completingActionIDs
                    )
                    // /m's md-callout margin-top clears the level card's
                    // downward overflow past the hero box.
                    .padding(.top, 128)

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
    // capped at --grad-span) ends well above the card's lower half, the
    // milestone nudge overlaps the card just under the wheel, and the callout
    // below compensates with a 128pt top margin.
    private func hero(_ snapshot: DashboardSnapshot, safeTop: CGFloat) -> some View {
        VStack(spacing: 0) {
            LevelWheelCard(level: snapshot.level) {
                onRoute(.achievements)
            }
            .padding(.bottom, -144)

            if let milestone = snapshot.nextMilestone {
                NextMilestoneView(milestone: milestone) {
                    onRoute(route(forMilestone: milestone.route))
                }
                .padding(.top, 8)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .padding(.bottom, 24)
        .frame(maxWidth: .infinity)
        .background {
            // Slice [headerHeight, gradientSpan) of the shared gradient fills
            // the hero box; the card's visual overflow below the box sits on
            // eggshell. Clipped so the offset slice never paints over the
            // header above.
            Color.clear
                .overlay(alignment: .top) {
                    Rectangle()
                        .fill(sharedGradient)
                        .frame(height: gradientSpan)
                        .offset(y: -(headerContentHeight + safeTop))
                }
                .clipped()
                .allowsHitTesting(false)
        }
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
        .padding(.top, 44)
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

    private func route(forPath path: String) -> AppRoute {
        switch path.trimmingCharacters(in: CharacterSet(charactersIn: "/")) {
        case "income": .income
        case "expenditure": .expenditure
        case "net-worth": .netWorth(category: nil)
        case "protection": .protection(policyType: nil, id: nil)
        case "savings": .savings(accountID: nil)
        case "investment": .investment(accountID: nil)
        case "retirement": .retirement(pensionType: nil, id: nil)
        case "estate": .estate
        case "goals": .goals
        case "tax", "tax-strategy": .taxStrategy
        case "holistic-plan": .holisticPlan
        case "achievements": .achievements
        default: .dashboard
        }
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
