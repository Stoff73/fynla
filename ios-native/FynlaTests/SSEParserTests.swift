import Foundation
import Testing
@testable import Fynla

@Suite("SSE parser")
struct SSEParserTests {
    @Test
    func parsesLFAndCRLFFramesWithIDsAndEventNames() throws {
        var parser = SSEParser()
        let input = "id: 17\r\nevent: fyn\r\ndata: first\r\n\r\nid: 18\ndata: second\n\n"

        let events = try parser.append(input.utf8) + parser.finish()

        #expect(events == [
            SSEEvent(id: "17", event: "fyn", data: "first"),
            SSEEvent(id: "18", event: nil, data: "second"),
        ])
    }

    @Test
    func parsesCRLFAcrossEveryByteSplit() throws {
        let bytes = [UInt8]("id: 17\r\nevent: fyn\r\ndata: first\r\n\r\n".utf8)
        let expected = [SSEEvent(id: "17", event: "fyn", data: "first")]

        for split in 0...bytes.count {
            #expect(try parse([
                Array(bytes[..<split]),
                Array(bytes[split...]),
            ]) == expected)
        }
    }

    @Test
    func ignoresOneLeadingUTF8BOMAcrossEveryByteSplit() throws {
        let bytes = [0xEF, 0xBB, 0xBF] + [UInt8]("data: first\n\n".utf8)
        let expected = [SSEEvent(id: nil, event: nil, data: "first")]

        for split in 0...bytes.count {
            #expect(try parse([
                Array(bytes[..<split]),
                Array(bytes[split...]),
            ]) == expected)
        }
    }

    @Test
    func joinsMultipleDataLinesAndIgnoresComments() throws {
        var parser = SSEParser()
        let input = ": keep-alive\nevent: fyn\ndata: {\"type\":\"unknown_future_frame\"}\ndata: raw-tail\n\n"

        let events = try parser.append(input.utf8) + parser.finish()

        #expect(events == [
            SSEEvent(
                id: nil,
                event: "fyn",
                data: "{\"type\":\"unknown_future_frame\"}\nraw-tail"
            ),
        ])
    }

    @Test
    func preservesTheLastEventIDAcrossFrames() throws {
        var parser = SSEParser()
        let input = "id: durable-id\ndata: first\n\ndata: second\n\n"

        let events = try parser.append(input.utf8) + parser.finish()

        #expect(events.map(\.id) == ["durable-id", "durable-id"])
    }

    @Test
    func emitsMultipleFramesFromOneChunkAndFinalFrameAtEOF() throws {
        var parser = SSEParser()
        let input = "data: one\n\ndata: two\n\ndata: final"

        let appended = try parser.append(input.utf8)
        let finished = try parser.finish()

        #expect(appended == [
            SSEEvent(id: nil, event: nil, data: "one"),
            SSEEvent(id: nil, event: nil, data: "two"),
        ])
        #expect(finished == [
            SSEEvent(id: nil, event: nil, data: "final"),
        ])
    }

    @Test
    func everyFixtureParsesIdenticallyAtEverySingleSplitPoint() throws {
        for name in ["multi-frame", "utf8", "unknown-frame"] {
            let bytes = [UInt8](try fixture(name))
            let expected = try parse([bytes])

            for split in 0...bytes.count {
                let chunks = [
                    Array(bytes[..<split]),
                    Array(bytes[split...]),
                ]
                #expect(
                    try parse(chunks) == expected,
                    "Fixture \(name) changed at byte split \(split)"
                )
            }
        }
    }

    @Test
    func decodesAMultiByteScalarSplitAcrossEveryByteBoundary() throws {
        let bytes = [UInt8]("data: Pension ✅ £25,000\n\n".utf8)
        let expected = [SSEEvent(id: nil, event: nil, data: "Pension ✅ £25,000")]

        for split in 0...bytes.count {
            #expect(try parse([
                Array(bytes[..<split]),
                Array(bytes[split...]),
            ]) == expected)
        }
    }

    @Test
    func rejectsInvalidUTF8InsteadOfReplacingBytes() {
        var parser = SSEParser()
        let invalid: [UInt8] = [
            0x64, 0x61, 0x74, 0x61, 0x3A, 0x20,
            0xC3, 0x28,
            0x0A, 0x0A,
        ]

        do {
            _ = try parser.append(invalid)
            Issue.record("Expected invalid UTF-8 to fail")
        } catch let error as SSEParserError {
            #expect(error == .invalidUTF8)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    @Test
    func rejectsAnOverlongLineBeforeItCanGrowWithoutBound() {
        var parser = SSEParser(maximumLineBytes: 8, maximumEventBytes: 32)

        do {
            _ = try parser.append("data: 1234".utf8)
            Issue.record("Expected an overlong line to fail")
        } catch let error as SSEParserError {
            #expect(error == .lineTooLong(maximumBytes: 8))
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    @Test
    func rejectsAnOversizedEventBeforeAccumulatingMoreDataLines() {
        var parser = SSEParser(maximumLineBytes: 32, maximumEventBytes: 5)

        do {
            _ = try parser.append("data: 123\ndata: 456\n\n".utf8)
            Issue.record("Expected an oversized event to fail")
        } catch let error as SSEParserError {
            #expect(error == .eventTooLarge(maximumBytes: 5))
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    private func parse(_ chunks: [[UInt8]]) throws -> [SSEEvent] {
        var parser = SSEParser()
        var events: [SSEEvent] = []
        for chunk in chunks {
            events += try parser.append(chunk)
        }
        events += try parser.finish()
        return events
    }

    private func fixture(_ name: String) throws -> Data {
        let bundle = Bundle(for: SSEFixtureBundleToken.self)
        if let url = bundle.url(
            forResource: name,
            withExtension: "sse",
            subdirectory: "Fixtures/SSE"
        ) ?? bundle.url(forResource: name, withExtension: "sse") {
            return try Data(contentsOf: url)
        }

        let url = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/SSE/\(name).sse")
        return try Data(contentsOf: url)
    }
}

private final class SSEFixtureBundleToken {}
