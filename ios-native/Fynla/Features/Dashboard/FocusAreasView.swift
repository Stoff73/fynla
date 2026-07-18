import SwiftUI

struct FocusAreasView: View {
    let areas: [DashboardFocusArea]
    let onAction: (DashboardAction) -> Void
    let onComplete: (DashboardAction) -> Void
    let completingActionIDs: Set<String>
    @State private var selection: String?

    private var selectedArea: DashboardFocusArea? {
        areas.first { $0.key == selection } ?? areas.first
    }

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Your focus areas")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)

            if areas.isEmpty {
                Text("Add more details to unlock your next actions.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            } else {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: FynlaSpacing.small) {
                        ForEach(areas) { area in
                            Button {
                                selection = area.key
                            } label: {
                                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                                    Text(area.label)
                                        .font(FynlaTypography.heading)
                                    Text(area.stat)
                                        .font(FynlaTypography.caption)
                                    if area.locked {
                                        Text("Locked")
                                            .font(FynlaTypography.caption.bold())
                                    }
                                }
                                .foregroundStyle(FynlaColor.primaryText)
                                .frame(minWidth: 150, alignment: .leading)
                                .padding(FynlaSpacing.medium)
                                .background(
                                    selection == area.key || (selection == nil && area.id == areas.first?.id)
                                        ? FynlaColor.Token.raspberry100.color
                                        : FynlaColor.surface
                                )
                                .overlay {
                                    RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                                        .stroke(FynlaColor.Token.horizon200.color)
                                }
                                .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                            }
                            .buttonStyle(.plain)
                            .accessibilityValue(area.locked ? "Locked" : "Available")
                        }
                    }
                }

                if let selectedArea {
                    VStack(spacing: FynlaSpacing.small) {
                        if selectedArea.actions.isEmpty {
                            Text("Nothing to action right now — add more details to unlock your next actions.")
                                .font(FynlaTypography.body)
                                .foregroundStyle(FynlaColor.secondaryText)
                                .frame(maxWidth: .infinity, alignment: .leading)
                        } else {
                            ForEach(selectedArea.actions) { action in
                                VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                                    Button {
                                        onAction(action)
                                    } label: {
                                        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                                            Text(action.title)
                                                .font(FynlaTypography.heading)
                                                .foregroundStyle(FynlaColor.primaryText)
                                            Text(action.meta)
                                                .font(FynlaTypography.bodySmall)
                                                .foregroundStyle(FynlaColor.secondaryText)
                                        }
                                        .frame(
                                            maxWidth: .infinity,
                                            minHeight: FynlaSpacing.minimumInteractiveTarget,
                                            alignment: .leading
                                        )
                                    }
                                    .buttonStyle(.plain)
                                    .accessibilityHint(
                                        action.action.kind == .fynCapture
                                            ? "Opens Fyn to collect these details"
                                            : "Opens the relevant financial screen"
                                    )

                                    if action.type == .recommendation {
                                        Button("Mark complete") {
                                            onComplete(action)
                                        }
                                        .font(FynlaTypography.button)
                                        .disabled(completingActionIDs.contains(action.id))
                                        .accessibilityIdentifier("dashboard.action.\(action.id).complete")
                                    }
                                }
                                .padding(.vertical, FynlaSpacing.small)
                            }
                        }
                    }
                    .padding(FynlaSpacing.standard)
                    .background(FynlaColor.surface)
                    .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                }
            }
        }
        .accessibilityIdentifier("dashboard.focus-areas")
    }
}
