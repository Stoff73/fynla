import Foundation

protocol HTTPTransport: Sendable {
    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse)
    func byteStream(for request: URLRequest) async throws -> HTTPByteStream
}

enum HTTPTransportError: Error, Sendable, Equatable {
    case byteBufferOverflow(maximumBytes: Int)
}

struct HTTPByteStream: Sendable {
    let response: HTTPURLResponse
    let bytes: AsyncThrowingStream<UInt8, Error>
}

actor URLSessionHTTPTransport: HTTPTransport {
    static let defaultMaximumBufferedBytes = 65_536

    private let session: URLSession
    private let maximumBufferedBytes: Int

    init(
        session: URLSession,
        maximumBufferedBytes: Int = URLSessionHTTPTransport.defaultMaximumBufferedBytes
    ) {
        precondition(maximumBufferedBytes > 0)
        self.session = session
        self.maximumBufferedBytes = maximumBufferedBytes
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

        let producer = bytes.task
        let stream = Self.boundedByteStream(
            from: bytes,
            maximumBufferedBytes: maximumBufferedBytes,
            cancelProducer: {
                producer.cancel()
            }
        )

        return HTTPByteStream(response: response, bytes: stream)
    }

    nonisolated static func boundedByteStream<Source>(
        from source: Source,
        maximumBufferedBytes: Int,
        cancelProducer: @escaping @Sendable () -> Void
    ) -> AsyncThrowingStream<UInt8, Error>
    where Source: AsyncSequence & Sendable, Source.Element == UInt8 {
        precondition(maximumBufferedBytes > 0)

        return AsyncThrowingStream<UInt8, Error>(
            bufferingPolicy: .bufferingOldest(maximumBufferedBytes)
        ) { continuation in
            let task = Task {
                do {
                    for try await byte in source {
                        try Task.checkCancellation()

                        switch continuation.yield(byte) {
                        case .enqueued:
                            continue
                        case .dropped:
                            cancelProducer()
                            continuation.finish(
                                throwing: HTTPTransportError.byteBufferOverflow(
                                    maximumBytes: maximumBufferedBytes
                                )
                            )
                            return
                        case .terminated:
                            cancelProducer()
                            return
                        @unknown default:
                            cancelProducer()
                            return
                        }
                    }
                    continuation.finish()
                } catch {
                    continuation.finish(throwing: error)
                }
            }

            continuation.onTermination = { termination in
                task.cancel()
                if case .cancelled = termination {
                    cancelProducer()
                }
            }
        }
    }
}
