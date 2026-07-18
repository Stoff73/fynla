import SwiftUI

struct BugReportView: View {
    @Bindable var model: BugReportModel

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.large) {
                Text("Report a problem")
                    .font(FynlaTypography.pageTitle)
                    .foregroundStyle(FynlaColor.primaryText)

                switch model.state {
                case .editing, .offline, .rateLimited, .failed:
                    editor
                case .reviewing, .submitting:
                    review
                case .submitted:
                    submitted
                }
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Report a problem")
        .navigationBarTitleDisplayMode(.inline)
        .accessibilityIdentifier("bug-report.screen")
    }

    private var editor: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Tell us what went wrong. You will review the technical details before anything is sent.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)

            Text("What went wrong?")
                .font(FynlaTypography.heading)
            TextField(
                "Describe what happened and what you were trying to do",
                text: $model.description,
                axis: .vertical
            )
            .lineLimit(4...10)
            .textFieldStyle(.roundedBorder)
            .accessibilityIdentifier("bug-report.description")

            Picker("Area", selection: $model.category) {
                ForEach(model.categories, id: \.self) { Text($0) }
            }
            Picker("How serious is it?", selection: $model.severity) {
                ForEach(model.severities, id: \.self) { Text($0) }
            }

            errorMessage

            Button("Review report") { model.prepareReview() }
                .disabled(!model.canReview)
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("bug-report.review")
        }
    }

    private var review: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Your report")
                .font(FynlaTypography.sectionTitle)
            Text(model.description)
                .font(FynlaTypography.body)
                .padding(FynlaSpacing.medium)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(FynlaColor.surface)

            Text("Technical details included")
                .font(FynlaTypography.sectionTitle)
            ForEach(model.reviewRows) { row in
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(row.label)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                    Text(row.value)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .textSelection(.enabled)
                }
            }
            Text("Conversation text, financial values, network contents, passwords, tokens and purchase signatures are not attached.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)

            HStack {
                Button("Edit") { model.edit() }
                    .disabled(model.state == .submitting)
                    .buttonStyle(.bordered)
                Button(model.state == .submitting ? "Sending…" : "Send report") {
                    Task { await model.submit() }
                }
                .disabled(model.state == .submitting)
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("bug-report.submit")
            }
        }
    }

    private var submitted: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Report logged")
                .font(FynlaTypography.sectionTitle)
            Text("Your report has been sent to our team. Thank you for helping us improve Fynla.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            Button("Report another problem") { model.reset() }
                .buttonStyle(.bordered)
        }
        .accessibilityIdentifier("bug-report.submitted")
    }

    @ViewBuilder
    private var errorMessage: some View {
        switch model.state {
        case .offline:
            Text("You're offline. Your report has not been sent.")
        case .rateLimited:
            Text("You've sent a few reports recently. Please try again later.")
        case .failed:
            Text("The report could not be sent. Please try again.")
        default:
            EmptyView()
        }
    }
}
