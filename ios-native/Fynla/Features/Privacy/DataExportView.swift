import SwiftUI
import UIKit

struct DataExportView: View {
    let model: DataExportModel
    @State private var presentsShare = false

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Data export")
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Fynla prepares a JSON copy of your account data. The temporary copy on this iPhone is removed after sharing.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)

            statusContent
            Spacer()
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(FynlaColor.pageBackground)
        .navigationTitle("Data export")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $presentsShare, onDismiss: {
            Task { await model.shareDismissed() }
        }) {
            if let url = model.shareURL {
                ExportShareSheet(url: url)
            }
        }
        .onChange(of: model.shareURL) { _, url in
            presentsShare = url != nil
        }
        .accessibilityIdentifier("privacy.export.screen")
    }

    @ViewBuilder
    private var statusContent: some View {
        switch model.state {
        case .idle:
            FynlaButton("Request data export") {
                Task { await model.requestExport() }
            }
            .accessibilityIdentifier("privacy.export.request")
        case .requesting:
            LoadingView(message: "Requesting your export…")
        case .processing:
            LoadingView(message: "Preparing your export…")
        case .ready:
            Text("Your export is ready. Choose where to share or save it.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityIdentifier("privacy.export.ready")
        case .expired:
            retryCard("This export has expired. Request a new copy.")
        case .rateLimited:
            retryCard("Too many export requests were made recently. Please wait and try again.")
        case .failed:
            retryCard("Your export could not be prepared or downloaded. Please try again.")
        }
    }

    private func retryCard(_ message: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
            FynlaButton("Try again", variant: .secondary) {
                Task { await model.requestExport() }
            }
        }
    }
}

private struct ExportShareSheet: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> UIActivityViewController {
        UIActivityViewController(activityItems: [url], applicationActivities: nil)
    }

    func updateUIViewController(
        _ uiViewController: UIActivityViewController,
        context: Context
    ) {}
}
