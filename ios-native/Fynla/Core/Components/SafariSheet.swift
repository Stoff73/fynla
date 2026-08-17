import SafariServices
import SwiftUI

struct SafariSheetItem: Identifiable {
    let url: URL
    var id: String { url.absoluteString }
}

struct SafariSheet: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> SFSafariViewController {
        SFSafariViewController(url: url)
    }

    func updateUIViewController(
        _ uiViewController: SFSafariViewController,
        context: Context
    ) {}
}
