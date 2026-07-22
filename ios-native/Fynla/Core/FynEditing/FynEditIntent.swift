import Foundation

struct FynEditIntent: Sendable, Equatable {
    static func message(
        updateScope: String,
        addPhrase: String,
        names: [String?]
    ) -> String {
        let clean = names
            .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
        return clean.isEmpty
            ? addPhrase
            : "I'd like to update my \(updateScope). I currently have: \(clean.joined(separator: ", "))."
    }
}
