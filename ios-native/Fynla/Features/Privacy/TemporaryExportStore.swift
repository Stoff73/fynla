import Foundation

actor TemporaryExportStore {
    private let directory: URL
    private let fileManager: FileManager
    private let prefix = "Fynla-Data-Export-"

    init(
        directory: URL = FileManager.default.temporaryDirectory,
        fileManager: FileManager = .default
    ) {
        self.directory = directory
        self.fileManager = fileManager
    }

    func save(_ data: Data, format: String) throws -> URL {
        try fileManager.createDirectory(
            at: directory,
            withIntermediateDirectories: true
        )
        let safeExtension = format == "csv" ? "csv" : "json"
        let url = directory.appending(
            path: "\(prefix)\(UUID().uuidString).\(safeExtension)"
        )
        try data.write(to: url, options: [.atomic, .completeFileProtection])
        return url
    }

    func delete(_ url: URL) throws {
        guard url.deletingLastPathComponent().standardizedFileURL
                == directory.standardizedFileURL,
              url.lastPathComponent.hasPrefix(prefix)
        else { return }
        if fileManager.fileExists(atPath: url.path) {
            try fileManager.removeItem(at: url)
        }
    }

    func cleanupExpired(now: Date = Date()) throws {
        guard fileManager.fileExists(atPath: directory.path) else { return }
        let urls = try fileManager.contentsOfDirectory(
            at: directory,
            includingPropertiesForKeys: [.contentModificationDateKey],
            options: [.skipsHiddenFiles]
        )
        for url in urls where url.lastPathComponent.hasPrefix(prefix) {
            let values = try url.resourceValues(forKeys: [.contentModificationDateKey])
            if let modified = values.contentModificationDate,
               now.timeIntervalSince(modified) >= 86_400 {
                try fileManager.removeItem(at: url)
            }
        }
    }

    func deleteAll() throws {
        guard fileManager.fileExists(atPath: directory.path) else { return }
        let urls = try fileManager.contentsOfDirectory(
            at: directory,
            includingPropertiesForKeys: nil,
            options: [.skipsHiddenFiles]
        )
        for url in urls where url.lastPathComponent.hasPrefix(prefix) {
            try fileManager.removeItem(at: url)
        }
    }
}
