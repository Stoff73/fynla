struct DiagnosticEvent: Sendable, Equatable {
    enum Category: String, Sendable {
        case application
        case authentication
        case network
        case streaming
    }

    let category: Category
    let operation: String
    let statusCode: Int?
    let requestID: String?
    let durationMilliseconds: Int?
}

enum DiagnosticOperation: String, CaseIterable, Sendable {
    case applicationLaunch = "app.launch"
    case applicationLock = "app.lock"
    case authenticationRefresh = "auth.refresh"
    case apiRequest = "api.request"
    case apiResponse = "api.response"
    case apiFailure = "api.failure"
    case streamConnect = "sse.connect"
    case streamDisconnect = "sse.disconnect"

    static let allowed = Set(allCases.map(\.rawValue))
}
