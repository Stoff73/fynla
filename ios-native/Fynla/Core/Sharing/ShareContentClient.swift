import Foundation
import SwiftUI
import UIKit

// Share content served by GET /api/v1/mobile/share/{type} — the same endpoint
// /m's doShare() uses (generic copy, no monetary values). The native share
// sheet presents whatever the server returns.
struct ShareContent: Decodable, Sendable, Equatable, Identifiable {
    let title: String?
    let text: String?
    let url: String?

    var id: String { (title ?? "") + (text ?? "") + (url ?? "") }

    var activityItems: [Any] {
        var items: [Any] = []
        let trimmedText = text?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        if !trimmedText.isEmpty {
            items.append(trimmedText)
        }
        if let url, let shareURL = URL(string: url) {
            items.append(shareURL)
        }
        if items.isEmpty {
            items.append(URL(string: "https://fynla.org")!)
        }
        return items
    }
}

protocol ShareContentClient: Sendable {
    func content(for shareType: String) async throws -> ShareContent
}

struct LiveShareContentClient: ShareContentClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func content(for shareType: String) async throws -> ShareContent {
        try await apiClient.send(
            APIRequest<ShareContent>(
                path: "api/v1/mobile/share/\(shareType)",
                method: .get
            )
        )
    }
}

struct ShareContentSheet: UIViewControllerRepresentable {
    let content: ShareContent

    func makeUIViewController(context: Context) -> UIActivityViewController {
        UIActivityViewController(
            activityItems: content.activityItems,
            applicationActivities: nil
        )
    }

    func updateUIViewController(
        _ uiViewController: UIActivityViewController,
        context: Context
    ) {}
}
