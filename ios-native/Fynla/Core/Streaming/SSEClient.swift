import Foundation

struct QueuedSSERequest: Decodable, Sendable, Equatable {
    let messageID: Int
    let queuePosition: Int?

    enum CodingKeys: String, CodingKey {
        case messageID = "message_id"
        case queuePosition = "queue_position"
    }
}

enum SSEStreamResult: Sendable {
    case stream(AsyncThrowingStream<SSEEvent, Error>)
    case queued(QueuedSSERequest)
}

enum SSEClientError: Error, Sendable, Equatable {
    case unexpectedStatus(Int, requestID: String?)
    case rateLimited(retryAfterSeconds: Double?, requestID: String?)
    case invalidQueuedResponse
    case queuedResponseTooLarge(maximumBytes: Int)
    case eventBufferOverflow(maximumEvents: Int)
}

actor SSEClient {
    static let defaultMaximumQueuedResponseBytes = 1_048_576
    static let defaultMaximumBufferedEvents = 64

    private let transport: any HTTPTransport
    private let maximumQueuedResponseBytes: Int
    private let maximumBufferedEvents: Int

    init(
        transport: any HTTPTransport,
        maximumQueuedResponseBytes: Int = SSEClient.defaultMaximumQueuedResponseBytes,
        maximumBufferedEvents: Int = SSEClient.defaultMaximumBufferedEvents
    ) {
        precondition(maximumQueuedResponseBytes > 0)
        precondition(maximumBufferedEvents > 0)
        self.transport = transport
        self.maximumQueuedResponseBytes = maximumQueuedResponseBytes
        self.maximumBufferedEvents = maximumBufferedEvents
    }

    func stream(_ request: URLRequest) async throws -> SSEStreamResult {
        let source = try await transport.byteStream(for: request)
        let status = source.response.statusCode

        if status == 202 {
            return .queued(try await queuedResult(from: source.bytes))
        }

        if status == 429 {
            throw SSEClientError.rateLimited(
                retryAfterSeconds: source.response
                    .value(forHTTPHeaderField: "Retry-After")
                    .flatMap(Double.init),
                requestID: source.response.value(forHTTPHeaderField: "X-Request-ID")
            )
        }

        guard (200..<300).contains(status) else {
            throw SSEClientError.unexpectedStatus(
                status,
                requestID: source.response.value(forHTTPHeaderField: "X-Request-ID")
            )
        }

        let upstream = source.bytes
        let maximumBufferedEvents = maximumBufferedEvents
        let stream = AsyncThrowingStream<SSEEvent, Error>(
            bufferingPolicy: .bufferingOldest(maximumBufferedEvents)
        ) { continuation in
            let task = Task {
                var parser = SSEParser()

                do {
                    for try await byte in upstream {
                        try Task.checkCancellation()
                        for event in try parser.append([byte]) {
                            try yield(
                                event,
                                to: continuation,
                                maximumBufferedEvents: maximumBufferedEvents
                            )
                        }
                    }

                    try Task.checkCancellation()
                    for event in try parser.finish() {
                        try yield(
                            event,
                            to: continuation,
                            maximumBufferedEvents: maximumBufferedEvents
                        )
                    }
                    continuation.finish()
                } catch is CancellationError {
                    continuation.finish()
                } catch {
                    continuation.finish(throwing: error)
                }
            }

            continuation.onTermination = { _ in
                task.cancel()
            }
        }

        return .stream(stream)
    }

    private func queuedResult(
        from bytes: AsyncThrowingStream<UInt8, Error>
    ) async throws -> QueuedSSERequest {
        var data = Data()
        data.reserveCapacity(256)

        for try await byte in bytes {
            try Task.checkCancellation()
            guard data.count < maximumQueuedResponseBytes else {
                throw SSEClientError.queuedResponseTooLarge(
                    maximumBytes: maximumQueuedResponseBytes
                )
            }
            data.append(byte)
        }
        try Task.checkCancellation()

        do {
            return try JSONDecoder().decode(QueuedSSERequest.self, from: data)
        } catch {
            throw SSEClientError.invalidQueuedResponse
        }
    }

    private nonisolated func yield(
        _ event: SSEEvent,
        to continuation: AsyncThrowingStream<SSEEvent, Error>.Continuation,
        maximumBufferedEvents: Int
    ) throws {
        switch continuation.yield(event) {
        case .enqueued:
            return
        case .dropped:
            throw SSEClientError.eventBufferOverflow(
                maximumEvents: maximumBufferedEvents
            )
        case .terminated:
            throw CancellationError()
        @unknown default:
            throw CancellationError()
        }
    }
}
