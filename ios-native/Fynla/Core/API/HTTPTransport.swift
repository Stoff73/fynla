import Foundation

protocol HTTPTransport: Sendable {
    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse)
    func byteStream(for request: URLRequest) async throws -> HTTPByteStream
}

struct HTTPByteStream: Sendable {
    let response: HTTPURLResponse
    let bytes: AsyncThrowingStream<UInt8, Error>
}

actor URLSessionHTTPTransport: HTTPTransport {
    private let session: URLSession

    init(session: URLSession) {
        self.session = session
    }

    static func ephemeral() -> URLSessionHTTPTransport {
        let configuration = URLSessionConfiguration.ephemeral
        configuration.urlCache = nil
        configuration.requestCachePolicy = .reloadIgnoringLocalCacheData
        configuration.httpShouldSetCookies = false
        return URLSessionHTTPTransport(session: URLSession(configuration: configuration))
    }

    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        let (data, response) = try await session.data(for: request)
        guard let response = response as? HTTPURLResponse else {
            throw URLError(.badServerResponse)
        }
        return (data, response)
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        let (bytes, response) = try await session.bytes(for: request)
        guard let response = response as? HTTPURLResponse else {
            throw URLError(.badServerResponse)
        }

        let stream = AsyncThrowingStream<UInt8, Error> { continuation in
            let task = Task {
                do {
                    for try await byte in bytes {
                        try Task.checkCancellation()
                        continuation.yield(byte)
                    }
                    continuation.finish()
                } catch {
                    continuation.finish(throwing: error)
                }
            }

            continuation.onTermination = { _ in
                task.cancel()
            }
        }

        return HTTPByteStream(response: response, bytes: stream)
    }
}
