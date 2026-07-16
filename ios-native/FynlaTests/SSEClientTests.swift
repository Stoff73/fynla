import Foundation
import Testing
@testable import Fynla

@Suite("SSE client")
struct SSEClientTests {
    @Test
    func streamsFramesAcrossChunksAndDoesNotStopAtDone() async throws {
        let transport = TestSSETransport(
            status: 200,
            chunks: [
                Data("event: fyn\ndata: before\n\nevent: do".utf8),
                Data("ne\ndata: {}\n\nevent: fyn\ndata: after\n\n".utf8),
            ]
        )
        let client = SSEClient(transport: transport)

        let result = try await client.stream(request())
        let stream = try requireStream(result)
        var events: [SSEEvent] = []
        for try await event in stream {
            events.append(event)
        }

        #expect(events == [
            SSEEvent(id: nil, event: "fyn", data: "before"),
            SSEEvent(id: nil, event: "done", data: "{}"),
            SSEEvent(id: nil, event: "fyn", data: "after"),
        ])
    }

    @Test
    func emitsFinalFrameWhenTheHTTPBodyEndsWithoutABlankLine() async throws {
        let transport = TestSSETransport(
            status: 200,
            chunks: [Data("event: fyn\ndata: final".utf8)]
        )
        let client = SSEClient(transport: transport)

        let stream = try requireStream(try await client.stream(request()))
        var events: [SSEEvent] = []
        for try await event in stream {
            events.append(event)
        }

        #expect(events == [SSEEvent(id: nil, event: "fyn", data: "final")])
    }

    @Test
    func returnsTypedQueuedResultFor202BeforeSSEParsing() async throws {
        let transport = TestSSETransport(
            status: 202,
            chunks: [Data(#"{"status":"queued","message_id":451,"queue_position":2}"#.utf8)]
        )
        let client = SSEClient(transport: transport)

        let result = try await client.stream(request())

        switch result {
        case let .queued(queued):
            #expect(queued == QueuedSSERequest(messageID: 451, queuePosition: 2))
        case .stream:
            Issue.record("Expected a typed queued result")
        }
    }

    @Test
    func rejectsMalformedAndOversizedQueuedResponses() async {
        let malformedClient = SSEClient(
            transport: TestSSETransport(
                status: 202,
                chunks: [Data("not-json".utf8)]
            )
        )

        do {
            _ = try await malformedClient.stream(request())
            Issue.record("Expected malformed queued JSON to fail")
        } catch let error as SSEClientError {
            #expect(error == .invalidQueuedResponse)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }

        let oversizedClient = SSEClient(
            transport: TestSSETransport(
                status: 202,
                chunks: [Data(#"{"message_id":451}"#.utf8)]
            ),
            maximumQueuedResponseBytes: 8
        )

        do {
            _ = try await oversizedClient.stream(request())
            Issue.record("Expected oversized queued JSON to fail")
        } catch let error as SSEClientError {
            #expect(error == .queuedResponseTooLarge(maximumBytes: 8))
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    @Test
    func cancellationWhileReadingAQueuedResponseStaysCancellation() async {
        let cancellation = CancellationProbe()
        let client = SSEClient(
            transport: TestSSETransport(
                status: 202,
                chunks: [Data(#"{"message_id":451"#.utf8)],
                finishes: false,
                cancellation: cancellation
            )
        )
        let operation = Task {
            try await client.stream(request())
        }

        await Task.yield()
        operation.cancel()

        do {
            _ = try await operation.value
            Issue.record("Expected cancellation")
        } catch is CancellationError {
            // Expected.
        } catch {
            Issue.record("Unexpected error: \(error)")
        }

        for _ in 0..<100 where !(await cancellation.wasCancelled()) {
            await Task.yield()
        }
        #expect(await cancellation.wasCancelled())
    }

    @Test
    func rejectsUnexpectedHTTPStatusBeforeParsing() async {
        let transport = TestSSETransport(
            status: 500,
            headers: ["X-Request-ID": "stream-request-7"],
            chunks: [Data("data: must-not-parse\n\n".utf8)]
        )
        let client = SSEClient(transport: transport)

        do {
            _ = try await client.stream(request())
            Issue.record("Expected an unexpected-status error")
        } catch let error as SSEClientError {
            #expect(error == .unexpectedStatus(500, requestID: "stream-request-7"))
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    @Test
    func downstreamCancellationCancelsTheUpstreamByteStream() async throws {
        let cancellation = CancellationProbe()
        let transport = TestSSETransport(
            status: 200,
            chunks: [Data("data: first\n\n".utf8)],
            finishes: false,
            cancellation: cancellation
        )
        let client = SSEClient(transport: transport)
        let stream = try requireStream(try await client.stream(request()))

        let consumer = Task {
            for try await _ in stream {}
        }
        await Task.yield()
        consumer.cancel()
        _ = try? await consumer.value

        for _ in 0..<100 where !(await cancellation.wasCancelled()) {
            await Task.yield()
        }
        #expect(await cancellation.wasCancelled())
    }

    @Test
    func boundedEventBufferFailsInsteadOfDroppingFramesSilently() async throws {
        let transport = TestSSETransport(
            status: 200,
            chunks: [Data("data: one\n\ndata: two\n\ndata: three\n\n".utf8)]
        )
        let client = SSEClient(transport: transport, maximumBufferedEvents: 1)
        let stream = try requireStream(try await client.stream(request()))

        for _ in 0..<100 {
            await Task.yield()
        }

        do {
            for try await _ in stream {}
            Issue.record("Expected bounded-buffer overflow")
        } catch let error as SSEClientError {
            #expect(error == .eventBufferOverflow(maximumEvents: 1))
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    @Test
    func propagatesTerminalUpstreamTransportErrors() async throws {
        let transport = TestSSETransport(
            status: 200,
            chunks: [Data("data: first\n\n".utf8)],
            failure: .disconnected
        )
        let client = SSEClient(transport: transport)
        let stream = try requireStream(try await client.stream(request()))

        do {
            for try await _ in stream {}
            Issue.record("Expected the upstream failure")
        } catch let error as TestSSEFailure {
            #expect(error == .disconnected)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    private func request() -> URLRequest {
        URLRequest(url: URL(string: "https://example.test/api/ai-chat/stream")!)
    }

    private func requireStream(
        _ result: SSEStreamResult
    ) throws -> AsyncThrowingStream<SSEEvent, Error> {
        switch result {
        case let .stream(stream):
            return stream
        case let .queued(queued):
            Issue.record("Expected a stream, received queued message \(queued.messageID)")
            throw SSEClientError.invalidQueuedResponse
        }
    }
}

private actor TestSSETransport: HTTPTransport {
    private let status: Int
    private let headers: [String: String]
    private let chunks: [Data]
    private let finishes: Bool
    private let cancellation: CancellationProbe?
    private let failure: TestSSEFailure?

    init(
        status: Int,
        headers: [String: String] = [:],
        chunks: [Data],
        finishes: Bool = true,
        cancellation: CancellationProbe? = nil,
        failure: TestSSEFailure? = nil
    ) {
        self.status = status
        self.headers = headers
        self.chunks = chunks
        self.finishes = finishes
        self.cancellation = cancellation
        self.failure = failure
    }

    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        throw URLError(.unsupportedURL)
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        let response = HTTPURLResponse(
            url: request.url!,
            statusCode: status,
            httpVersion: "HTTP/1.1",
            headerFields: headers
        )!
        let chunks = chunks
        let finishes = finishes
        let cancellation = cancellation
        let failure = failure
        let bytes = AsyncThrowingStream<UInt8, Error> { continuation in
            continuation.onTermination = { _ in
                if let cancellation {
                    Task { await cancellation.markCancelled() }
                }
            }
            for chunk in chunks {
                for byte in chunk {
                    continuation.yield(byte)
                }
            }
            if let failure {
                continuation.finish(throwing: failure)
            } else if finishes {
                continuation.finish()
            }
        }
        return HTTPByteStream(response: response, bytes: bytes)
    }
}

private enum TestSSEFailure: Error, Sendable, Equatable {
    case disconnected
}

private actor CancellationProbe {
    private var cancelled = false

    func markCancelled() {
        cancelled = true
    }

    func wasCancelled() -> Bool {
        cancelled
    }
}
