import SwiftUI

// Transcribes /m's "LEVEL UP" callout (md-callout in Dashboard.vue +
// dashboard.css): raspberry gradient card whose top half holds the LEVEL UP
// mark and percentile copy, and whose light-pink lower half holds the
// swipeable focus-area carousel (one card per view), arrows + dots, and the
// actions list for the active card.
struct FocusAreasView: View {
    let areas: [DashboardFocusArea]
    let percentile: Int
    let onAction: (DashboardAction) -> Void
    let onComplete: (DashboardAction) -> Void
    let onViewAll: (String) -> Void
    let onSeeAllActions: () -> Void
    let completingActionIDs: Set<String>
    @State private var activeIndex = 0
    // Mirrors /m: skipped recommendations hide for the session only and
    // reappear once no other recommendations remain on the card.
    @State private var skippedIDs: Set<String> = []

    private var activeArea: DashboardFocusArea? {
        guard areas.indices.contains(activeIndex) else { return areas.first }
        return areas[activeIndex]
    }

    private func visibleActions(in area: DashboardFocusArea) -> [DashboardAction] {
        let remaining = area.actions.filter { !skippedIDs.contains($0.id) }
        return remaining.isEmpty ? area.actions : remaining
    }

    var body: some View {
        VStack(spacing: 0) {
            topHalf
            lowerHalf
        }
        .background(
            LinearGradient(
                colors: [
                    FynlaColor.Token.raspberry500.color,
                    FynlaColor.Token.raspberry600.color,
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .shadow(
            color: FynlaColor.Token.raspberry500.color.opacity(0.28),
            radius: 9,
            y: 6
        )
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("dashboard.focus-areas")
    }

    // Top half (md-callout__top): LEVEL UP mark left, rank copy right.
    private var topHalf: some View {
        HStack(alignment: .center, spacing: 16) {
            Text("LEVEL\nUP")
                .font(.system(size: 26, weight: .black))
                .foregroundStyle(.white)
                .lineSpacing(0)
                .fixedSize()

            VStack(alignment: .leading, spacing: 2) {
                (
                    Text("You're ahead of ")
                    + Text("\(percentile)%").fontWeight(.black)
                    + Text(" of people")
                )
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(.white)
                Text("Complete your actions to get further ahead, level up and change your financial future")
                    .font(.system(size: 13))
                    .foregroundStyle(.white.opacity(0.92))
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.horizontal, 18)
        .padding(.top, 18)
        .padding(.bottom, 16)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("You're ahead of \(percentile) percent of people")
    }

    // Lower half (md-callout__carousel + md-recs): light-pink surface with the
    // carousel, its navigation, and the active card's actions.
    private var lowerHalf: some View {
        VStack(spacing: 0) {
            if areas.isEmpty {
                emptyRow(
                    "Nothing to action right now — add more details to unlock your next actions."
                )
                .padding(.top, 18)
            } else {
                carousel
                    .padding(.top, 18)

                carouselNavigation
                    .padding(.top, 14)

                if let activeArea {
                    actionsPanel(for: activeArea)
                        .padding(.top, 20)
                }
            }
        }
        .padding(.horizontal, 18)
        .padding(.bottom, 18)
        .frame(maxWidth: .infinity)
        .background(FynlaColor.Token.lightPink50.color)
    }

    // One focus card per view, swipeable (md-focus scroll-snap carousel).
    private var carousel: some View {
        TabView(selection: $activeIndex) {
            ForEach(Array(areas.enumerated()), id: \.element.id) { index, area in
                focusCard(area, isActive: index == activeIndex)
                    .tag(index)
            }
        }
        .tabViewStyle(.page(indexDisplayMode: .never))
        .frame(height: 104)
    }

    private func focusCard(_ area: DashboardFocusArea, isActive: Bool) -> some View {
        VStack(spacing: 4) {
            Text(area.label)
                .font(.system(size: 22, weight: .black))
                .foregroundStyle(
                    area.locked
                        ? FynlaColor.Token.neutral500.color
                        : (isActive
                            ? FynlaColor.Token.raspberry500.color
                            : FynlaColor.Token.horizon600.color)
                )
                .lineLimit(1)
            Text(area.stat)
                .font(.system(size: 13, weight: .semibold))
                .foregroundStyle(
                    area.locked
                        ? FynlaColor.Token.neutral500.color
                        : (isActive
                            ? FynlaColor.Token.raspberry600.color
                            : FynlaColor.Token.neutral500.color)
                )
                .lineLimit(1)
            if area.locked {
                Text("LOCKED")
                    .font(.system(size: 10, weight: .bold))
                    .kerning(0.6)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 2)
                    .background(FynlaColor.Token.eggshell500.color)
                    .clipShape(Capsule())
                    .padding(.top, 4)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(16)
        .background(
            area.locked
                ? FynlaColor.Token.lightGray.color
                : FynlaColor.Token.eggshell500.color
        )
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(
                    isActive && !area.locked
                        ? FynlaColor.Token.raspberry500.color
                        : FynlaColor.Token.horizon100.color,
                    lineWidth: 1
                )
        )
        .shadow(
            color: isActive && !area.locked ? .black.opacity(0.08) : .clear,
            radius: 7,
            y: 4
        )
        .accessibilityValue(area.locked ? "Locked" : "Available")
    }

    // Prev/next arrows (white circles, raspberry chevrons) + paging dots
    // (light-pink inactive, raspberry capsule active), md-callout__nav.
    private var carouselNavigation: some View {
        HStack(spacing: 12) {
            Button {
                withAnimation { activeIndex = max(0, activeIndex - 1) }
            } label: {
                Image(systemName: "chevron.left")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .frame(width: 28, height: 28)
                    .background(Color.white)
                    .clipShape(Circle())
            }
            .disabled(activeIndex == 0)
            .opacity(activeIndex == 0 ? 0.4 : 1)
            .accessibilityLabel("Previous focus area")

            HStack(spacing: 8) {
                ForEach(areas.indices, id: \.self) { index in
                    Button {
                        withAnimation { activeIndex = index }
                    } label: {
                        Capsule()
                            .fill(
                                index == activeIndex
                                    ? FynlaColor.Token.raspberry500.color
                                    : FynlaColor.Token.lightPink200.color
                            )
                            .frame(width: index == activeIndex ? 20 : 8, height: 8)
                    }
                    .accessibilityLabel(areas[index].label)
                    .accessibilityAddTraits(index == activeIndex ? .isSelected : [])
                }
            }

            Button {
                withAnimation { activeIndex = min(areas.count - 1, activeIndex + 1) }
            } label: {
                Image(systemName: "chevron.right")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .frame(width: 28, height: 28)
                    .background(Color.white)
                    .clipShape(Circle())
            }
            .disabled(activeIndex >= areas.count - 1)
            .opacity(activeIndex >= areas.count - 1 ? 0.4 : 1)
            .accessibilityLabel("Next focus area")
        }
        .frame(maxWidth: .infinity)
    }

    // Actions for the selected card (md-recs).
    private func actionsPanel(for area: DashboardFocusArea) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            if area.actions.isEmpty {
                emptyRow(
                    "Nothing to action right now — add more details to unlock your next actions."
                )
            } else {
                ForEach(visibleActions(in: area)) { action in
                    actionRow(action)
                }
            }

            if !area.locked, area.key != "top" {
                Button("View all \(area.label.lowercased())") {
                    onViewAll(area.key)
                }
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(FynlaColor.Token.raspberry600.color)
                .padding(.top, 4)
                .frame(minHeight: 32, alignment: .leading)
                .accessibilityIdentifier("dashboard.focus-areas.view-all")
            }

            Button("See all actions") {
                onSeeAllActions()
            }
            .font(.system(size: 13, weight: .bold))
            .foregroundStyle(FynlaColor.Token.raspberry600.color)
            .frame(maxWidth: .infinity, minHeight: 32)
            .padding(.top, 8)
            .overlay(alignment: .top) {
                FynlaColor.Token.lightPink100.color.frame(height: 1)
            }
            .accessibilityIdentifier("dashboard.focus-areas.see-all")
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func emptyRow(_ text: String) -> some View {
        Text(text)
            .font(.system(size: 15, weight: .semibold))
            .foregroundStyle(FynlaColor.Token.horizon600.color)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .background(FynlaColor.Token.eggshell500.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // One action row (md-rec): check zone with divider, key/bulb lead glyph,
    // title + meta, raspberry chevron, dismiss zone. Done rows go savannah +
    // spring; unlock rows carry the dotted border.
    private func actionRow(_ action: DashboardAction) -> some View {
        let isCompleting = completingActionIDs.contains(action.id)
        let showTick = action.done || isCompleting
        let isUnlock = action.type == .unlock

        return HStack(spacing: 0) {
            if action.type == .recommendation {
                Button {
                    onComplete(action)
                } label: {
                    ZStack {
                        Circle()
                            .fill(
                                showTick
                                    ? FynlaColor.Token.spring500.color
                                    : FynlaColor.Token.horizon100.color
                            )
                        Circle()
                            .stroke(
                                showTick
                                    ? FynlaColor.Token.spring500.color
                                    : FynlaColor.Token.horizon200.color,
                                lineWidth: 2
                            )
                        if showTick {
                            Image(systemName: "checkmark")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundStyle(.white)
                        }
                    }
                    .frame(width: 24, height: 24)
                    .frame(width: 52)
                    .frame(maxHeight: .infinity)
                }
                .disabled(isCompleting)
                .accessibilityLabel(showTick ? "Mark as not done" : "Mark complete")
                .accessibilityIdentifier("dashboard.action.\(action.id).complete")

                (showTick
                    ? FynlaColor.Token.spring400.color
                    : FynlaColor.Token.lightGray.color)
                    .frame(width: 1)
            }

            Button {
                onAction(action)
            } label: {
                HStack(spacing: 12) {
                    Image(systemName: isUnlock ? "key" : "lightbulb")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundStyle(
                            isUnlock
                                ? FynlaColor.Token.neutral400.color
                                : FynlaColor.Token.raspberry500.color
                        )
                        .accessibilityHidden(true)

                    VStack(alignment: .leading, spacing: 2) {
                        Text(action.title)
                            .font(.system(size: 15, weight: .semibold))
                            .foregroundStyle(
                                action.done
                                    ? FynlaColor.Token.horizon400.color
                                    : (isUnlock
                                        ? FynlaColor.Token.horizon500.color
                                        : FynlaColor.Token.horizon600.color)
                            )
                            .strikethrough(action.done)
                            .fixedSize(horizontal: false, vertical: true)
                        Text(action.meta)
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.horizon400.color)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)

                    Image(systemName: "chevron.right")
                        .font(.system(size: 13, weight: .semibold))
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                        .accessibilityHidden(true)
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 14)
            }
            .buttonStyle(.plain)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(
                action.meta.isEmpty
                    ? action.title
                    : "\(action.title). \(action.meta)"
            )
            .accessibilityHint(
                action.action.kind == .fynCapture
                    ? "Opens Fyn to collect these details"
                    : "Opens the relevant financial screen"
            )
            .accessibilityIdentifier("dashboard.action.\(action.id).open")

            if action.type == .recommendation {
                FynlaColor.Token.lightPink100.color.frame(width: 1)

                Button {
                    skippedIDs.insert(action.id)
                } label: {
                    Image(systemName: "xmark")
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundStyle(FynlaColor.Token.neutral400.color)
                        .frame(width: 40)
                        .frame(maxHeight: .infinity)
                }
                .accessibilityLabel("Skip this recommendation for now")
                .accessibilityIdentifier("dashboard.action.\(action.id).skip")
            }
        }
        .fixedSize(horizontal: false, vertical: true)
        .background(
            action.done
                ? FynlaColor.Token.savannah100.color
                : FynlaColor.Token.eggshell500.color
        )
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay {
            if action.done {
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .stroke(FynlaColor.Token.spring400.color, lineWidth: 1)
            } else if isUnlock {
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .stroke(
                        FynlaColor.Token.horizon300.color,
                        style: StrokeStyle(lineWidth: 1, dash: [1, 3])
                    )
            }
        }
        .opacity(isCompleting ? 0.4 : 1)
    }
}
