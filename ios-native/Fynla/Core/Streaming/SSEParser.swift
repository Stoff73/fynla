enum SSEParserError: Error, Sendable, Equatable {
    case invalidUTF8
    case lineTooLong(maximumBytes: Int)
    case eventTooLarge(maximumBytes: Int)
}

struct SSEParser: Sendable {
    static let defaultMaximumLineBytes = 65_536
    static let defaultMaximumEventBytes = 1_048_576

    private let maximumLineBytes: Int
    private let maximumEventBytes: Int
    private var buffer: [UInt8] = []
    private var isAtStreamStart = true
    private var lastEventID: String?
    private var eventName: String?
    private var dataLines: [String] = []
    private var dataByteCount = 0
    private var hasDataField = false

    init(
        maximumLineBytes: Int = Self.defaultMaximumLineBytes,
        maximumEventBytes: Int = Self.defaultMaximumEventBytes
    ) {
        precondition(maximumLineBytes > 0)
        precondition(maximumEventBytes > 0)
        self.maximumLineBytes = maximumLineBytes
        self.maximumEventBytes = maximumEventBytes
    }

    mutating func append(_ bytes: some Sequence<UInt8>) throws -> [SSEEvent] {
        var events: [SSEEvent] = []

        for byte in bytes {
            if buffer.last == 0x0D {
                if byte == 0x0A {
                    buffer.removeLast()
                    events += try processBufferedLine()
                    continue
                }

                buffer.removeLast()
                events += try processBufferedLine()
            }

            if byte == 0x0A {
                events += try processBufferedLine()
            } else {
                buffer.append(byte)
                let pendingCarriageReturn = byte == 0x0D ? 1 : 0
                guard buffer.count - pendingCarriageReturn <= maximumLineBytes else {
                    throw SSEParserError.lineTooLong(maximumBytes: maximumLineBytes)
                }
            }
        }

        return events
    }

    mutating func finish() throws -> [SSEEvent] {
        var events: [SSEEvent] = []

        if buffer.last == 0x0D {
            buffer.removeLast()
            events += try processBufferedLine()
        } else if !buffer.isEmpty {
            events += try processBufferedLine()
        }

        if let event = dispatchEvent() {
            events.append(event)
        }
        return events
    }

    private mutating func processBufferedLine() throws -> [SSEEvent] {
        guard var line = String(bytes: buffer, encoding: .utf8) else {
            throw SSEParserError.invalidUTF8
        }
        buffer.removeAll(keepingCapacity: true)

        if isAtStreamStart {
            isAtStreamStart = false
            if line.first == "\u{FEFF}" {
                line.removeFirst()
            }
        }

        return try process(line)
    }

    private mutating func process(_ line: String) throws -> [SSEEvent] {
        if line.isEmpty {
            return dispatchEvent().map { [$0] } ?? []
        }

        guard !line.hasPrefix(":") else {
            return []
        }

        let field: Substring
        var value: Substring
        if let colon = line.firstIndex(of: ":") {
            field = line[..<colon]
            value = line[line.index(after: colon)...]
            if value.first == " " {
                value.removeFirst()
            }
        } else {
            field = Substring(line)
            value = ""
        }

        switch field {
        case "data":
            let separatorBytes = hasDataField ? 1 : 0
            let nextDataByteCount = dataByteCount + separatorBytes + value.utf8.count
            guard nextDataByteCount <= maximumEventBytes else {
                throw SSEParserError.eventTooLarge(maximumBytes: maximumEventBytes)
            }
            hasDataField = true
            dataByteCount = nextDataByteCount
            dataLines.append(String(value))
        case "event":
            eventName = value.isEmpty ? nil : String(value)
        case "id" where !value.contains("\0"):
            lastEventID = String(value)
        default:
            break
        }

        return []
    }

    private mutating func dispatchEvent() -> SSEEvent? {
        defer {
            eventName = nil
            dataLines.removeAll(keepingCapacity: true)
            dataByteCount = 0
            hasDataField = false
        }

        guard hasDataField else {
            return nil
        }

        return SSEEvent(
            id: lastEventID,
            event: eventName,
            data: dataLines.joined(separator: "\n")
        )
    }
}
