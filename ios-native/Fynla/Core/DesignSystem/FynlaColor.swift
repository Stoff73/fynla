import SwiftUI

enum FynlaColor {
    enum Token: String, CaseIterable, Sendable {
        case eggshell50 = "Eggshell50"
        case eggshell500 = "Eggshell500"
        case horizon100 = "Horizon100"
        case horizon200 = "Horizon200"
        case horizon300 = "Horizon300"
        case horizon400 = "Horizon400"
        case horizon500 = "Horizon500"
        case horizon600 = "Horizon600"
        case lightBlue100 = "LightBlue100"
        // /m's --light-gray; "LightGray" would collide with UIColor.lightGray
        // in the generated asset symbols.
        case lightGray = "LightGrey"
        case lightPink50 = "LightPink50"
        case lightPink100 = "LightPink100"
        case lightPink200 = "LightPink200"
        case loginGradientMid = "LoginGradientMid"
        case loginGradientBottom = "LoginGradientBottom"
        case neutral400 = "Neutral400"
        case neutral500 = "Neutral500"
        case neutral600 = "Neutral600"
        case raspberry100 = "Raspberry100"
        case raspberry300 = "Raspberry300"
        case raspberry400 = "Raspberry400"
        case raspberry500 = "Raspberry500"
        case raspberry600 = "Raspberry600"
        case raspberry700 = "Raspberry700"
        case raspberry800 = "Raspberry800"
        case savannah100 = "Savannah100"
        case spring100 = "Spring100"
        case spring400 = "Spring400"
        case spring500 = "Spring500"
        case spring600 = "Spring600"
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
    static let successToken = Token.spring500

    static var primaryAction: Color { primaryActionToken.color }
    static var primaryText: Color { primaryTextToken.color }
    static var secondaryText: Color { secondaryTextToken.color }
    static var pageBackground: Color { pageBackgroundToken.color }
    static var surface: Color { surfaceToken.color }
    static var focus: Color { focusToken.color }
    static var success: Color { successToken.color }
}
