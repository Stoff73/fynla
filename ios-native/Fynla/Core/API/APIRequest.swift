import Foundation

enum HTTPMethod: String, Sendable {
    case get = "GET"
    case head = "HEAD"
    case post = "POST"
    case put = "PUT"
    case patch = "PATCH"
    case delete = "DELETE"

    var permitsAuthenticationReplay: Bool {
        self == .get || self == .head
    }
}

enum APIResponseDecoding: Sendable {
    case envelope
    case raw
}

struct APIRequest<Response: Decodable & Sendable>: Sendable {
    let path: String
    let method: HTTPMethod
    let queryItems: [URLQueryItem]
    let body: Data?
    let headers: [String: String]
    let responseDecoding: APIResponseDecoding

    init(
        path: String,
        method: HTTPMethod,
        queryItems: [URLQueryItem] = [],
        body: Data? = nil,
        headers: [String: String] = [:],
        responseDecoding: APIResponseDecoding = .envelope
    ) {
        self.path = path
        self.method = method
        self.queryItems = queryItems
        self.body = body
        self.headers = headers
        self.responseDecoding = responseDecoding
    }

    func urlRequest(
        baseURL: URL,
        clientName: String,
        version: String,
        build: String,
        requestID: String,
        accessToken: String?
    ) throws -> URLRequest {
        var url = baseURL
        for component in path.split(separator: "/", omittingEmptySubsequences: true) {
            url.append(path: String(component))
        }

        if !queryItems.isEmpty {
            guard var components = URLComponents(url: url, resolvingAgainstBaseURL: false) else {
                throw URLError(.badURL)
            }
            components.queryItems = queryItems
            guard let queryURL = components.url else {
                throw URLError(.badURL)
            }
            url = queryURL
        }

        var request = URLRequest(url: url)
        request.httpMethod = method.rawValue
        request.httpBody = body

        for (name, value) in headers {
            request.setValue(value, forHTTPHeaderField: name)
        }

        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue(clientName, forHTTPHeaderField: "X-Fynla-Client")
        request.setValue(version, forHTTPHeaderField: "X-Fynla-Version")
        request.setValue(build, forHTTPHeaderField: "X-Fynla-Build")
        request.setValue(requestID, forHTTPHeaderField: "X-Request-ID")

        if body != nil && request.value(forHTTPHeaderField: "Content-Type") == nil {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        }

        if let accessToken, !accessToken.isEmpty {
            request.setValue("Bearer \(accessToken)", forHTTPHeaderField: "Authorization")
        } else {
            request.setValue(nil, forHTTPHeaderField: "Authorization")
        }

        return request
    }
}
