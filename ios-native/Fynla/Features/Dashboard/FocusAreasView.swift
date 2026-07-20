import SwiftUI

// Mirrors /m's "LEVEL UP" callout (md-callout in Dashboard.vue): raspberry
// gradient card holding the percentile statement, the swipeable focus-area
// carousel with dots and arrows, and the actions list for the active card.
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
        VStack(spacing: FynlaSpacing.standard) {
            HStack(alignment: .top, spacing: FynlaSpacing.standard) {
                Text("LEVEL\nUP")
                    .font(FynlaTypography.sectionTitle.weight(.black))
                    .foregroundStyle(FynlaColor.surface)
                    .multilineTextAlignment(.leading)

                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    (
                        Text("You're ahead of ")
                        + Text("\(percentile)%").fontWeight(.bold)
                        + Text(" of people")
                    )
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.surface)
                    Text("Complete your actions to get further ahead, level up and change your financial future")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.surface.opacity(0.85))
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .accessibilityElement(children: .combine)
            .accessibilityLabel("You're ahead of \(percentile) percent of people")

            if areas.isEmpty {
                Text("Add more details to unlock your next actions.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.surface)
                    .frame(maxWidth: .infinity, alignment: .leading)
            } else {
                carousel

                carouselNavigation

                if let activeArea {
                    actionsPanel(for: activeArea)
                }
            }
        }
        .padding(FynlaSpacing.standard)
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
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.cardCornerRadius, style: .continuous))
        .accessibilityIdentifier("dashboard.focus-areas")
    }

    private var carousel: some View {
        TabView(selection: $activeIndex) {
            ForEach(Array(areas.enumerated()), id: \.element.id) { index, area in
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(area.label)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(area.stat)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.secondaryText)
                    if area.locked {
                        Text("Locked")
                            .font(FynlaTypography.caption.bold())
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(FynlaSpacing.standard)
                .background(FynlaColor.surface)
                .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
                .tag(index)
                .accessibilityValue(area.locked ? "Locked" : "Available")
            }
        }
        .tabViewStyle(.page(indexDisplayMode: .never))
        .frame(height: 96)
    }

    // Prev/next arrows + paging dots, matching /m's md-callout__nav.
    // Chevron glyphs mirror /m's SVG arrows (CSJ /m design match, 2026-07-20).
    private var carouselNavigation: some View {
        HStack(spacing: FynlaSpacing.standard) {
            Button {
                withAnimation { activeIndex = max(0, activeIndex - 1) }
            } label: {
                Image(systemName: "chevron.left")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.surface)
                    .frame(
                        width: FynlaSpacing.minimumInteractiveTarget,
                        height: FynlaSpacing.minimumInteractiveTarget
                    )
            }
            .disabled(activeIndex == 0)
            .opacity(activeIndex == 0 ? 0.4 : 1)
            .accessibilityLabel("Previous focus area")

            HStack(spacing: FynlaSpacing.small) {
                ForEach(areas.indices, id: \.self) { index in
                    Button {
                        withAnimation { activeIndex = index }
                    } label: {
                        Circle()
                            .fill(
                                index == activeIndex
                                    ? FynlaColor.surface
                                    : FynlaColor.surface.opacity(0.4)
                            )
                            .frame(width: 8, height: 8)
                    }
                    .accessibilityLabel(areas[index].label)
                    .accessibilityAddTraits(index == activeIndex ? .isSelected : [])
                }
            }

            Button {
                withAnimation { activeIndex = min(areas.count - 1, activeIndex + 1) }
            } label: {
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.surface)
                    .frame(
                        width: FynlaSpacing.minimumInteractiveTarget,
                        height: FynlaSpacing.minimumInteractiveTarget
                    )
            }
            .disabled(activeIndex >= areas.count - 1)
            .opacity(activeIndex >= areas.count - 1 ? 0.4 : 1)
            .accessibilityLabel("Next focus area")
        }
        .frame(maxWidth: .infinity)
    }

    private func actionsPanel(for area: DashboardFocusArea) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            if area.actions.isEmpty {
                Text("Nothing to action right now — add more details to unlock your next actions.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .frame(maxWidth: .infinity, alignment: .leading)
            } else {
                ForEach(visibleActions(in: area)) { action in
                    actionRow(action)
                }
            }

            if !area.locked, area.key != "top" {
                Button("View all \(area.label.lowercased())") {
                    onViewAll(area.key)
                }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryAction)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("dashboard.focus-areas.view-all")
            }

            Button("See all actions") {
                onSeeAllActions()
            }
            .font(FynlaTypography.button)
            .foregroundStyle(FynlaColor.primaryAction)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .accessibilityIdentifier("dashboard.focus-areas.see-all")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
    }

    // One action row, matching /m's md-rec: check toggle for recommendations,
    // key/bulb lead glyph (functional differentiation, CSJ-approved on /m),
    // title + meta, trailing chevron, session-local skip.
    private func actionRow(_ action: DashboardAction) -> some View {
        let isCompleting = completingActionIDs.contains(action.id)
        let showTick = action.done || isCompleting

        return HStack(alignment: .center, spacing: FynlaSpacing.medium) {
            if action.type == .recommendation {
                Button {
                    onComplete(action)
                } label: {
                    ZStack {
                        Circle()
                            .fill(
                                showTick
                                    ? FynlaColor.primaryAction
                                    : FynlaColor.Token.horizon100.color
                            )
                        Circle()
                            .stroke(FynlaColor.Token.horizon200.color, lineWidth: 2)
                        if showTick {
                            Image(systemName: "checkmark")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundStyle(FynlaColor.surface)
                        }
                    }
                    .frame(width: 24, height: 24)
                }
                .disabled(isCompleting)
                .accessibilityLabel(showTick ? "Mark as not done" : "Mark complete")
                .accessibilityIdentifier("dashboard.action.\(action.id).complete")
            }

            Button {
                onAction(action)
            } label: {
                HStack(spacing: FynlaSpacing.medium) {
                    Image(systemName: action.type == .unlock ? "key" : "lightbulb")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundStyle(
                            action.type == .unlock
                                ? FynlaColor.secondaryText
                                : FynlaColor.primaryAction
                        )
                        .accessibilityHidden(true)

                    VStack(alignment: .leading, spacing: 2) {
                        Text(action.title)
                            .font(FynlaTypography.bodySmall.weight(.semibold))
                            .foregroundStyle(FynlaColor.primaryText)
                        Text(action.meta)
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)

                    Image(systemName: "chevron.right")
                        .font(.system(size: 13, weight: .semibold))
                        .foregroundStyle(FynlaColor.secondaryText)
                        .accessibilityHidden(true)
                }
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            }
            .buttonStyle(.plain)
            .accessibilityHint(
                action.action.kind == .fynCapture
                    ? "Opens Fyn to collect these details"
                    : "Opens the relevant financial screen"
            )

            if action.type == .recommendation {
                Button {
                    skippedIDs.insert(action.id)
                } label: {
                    Image(systemName: "xmark")
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundStyle(FynlaColor.secondaryText)
                        .frame(
                            width: FynlaSpacing.minimumInteractiveTarget,
                            height: FynlaSpacing.minimumInteractiveTarget
                        )
                }
                .accessibilityLabel("Skip this recommendation for now")
                .accessibilityIdentifier("dashboard.action.\(action.id).skip")
            }
        }
        .padding(.horizontal, FynlaSpacing.medium)
        .padding(.vertical, FynlaSpacing.small)
        .background(FynlaColor.pageBackground)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
        .opacity(isCompleting ? 0.4 : 1)
    }
}
