import SwiftUI

enum FynlaColor {
    enum Token: String, CaseIterable, Sendable {
        case eggshell50 = "Eggshell50"
        case eggshell500 = "Eggshell500"
        case horizon200 = "Horizon200"
        case horizon500 = "Horizon500"
        case neutral500 = "Neutral500"
        case raspberry100 = "Raspberry100"
        case raspberry500 = "Raspberry500"
        case raspberry600 = "Raspberry600"
        case raspberry700 = "Raspberry700"
        case raspberry800 = "Raspberry800"
        case savannah100 = "Savannah100"
        case violet500 = "Violet500"

        var assetName: String { rawValue }
        var color: Color { Color(assetName) }
    }

    static let primaryActionToken = Token.raspberry500
    static let primaryTextToken = Token.horizon500
    static let secondaryTextToken = Token.neutral500
    static let pageBackgroundToken = Token.eggshell500
    static let surfaceToken = Token.eggshell50
    static let focusToken = Token.violet500

    static var primaryAction: Color { primaryActionToken.color }
    static var primaryText: Color { primaryTextToken.color }
    static var secondaryText: Color { secondaryTextToken.color }
    static var pageBackground: Color { pageBackgroundToken.color }
    static var surface: Color { surfaceToken.color }
    static var focus: Color { focusToken.color }
}
