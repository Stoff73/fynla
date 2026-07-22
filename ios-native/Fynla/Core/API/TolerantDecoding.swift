import Foundation

// The Laravel module endpoints serialise Eloquent decimal columns as strings
// ("40000.00") and some null fields as empty objects ({}). /m's JavaScript
// coerces both silently (Number("40000.00"), falsy {} guards); the native
// synthesized decoders must match that tolerance or live module screens fail
// to decode. These concrete overloads win overload resolution against the
// generic decode<T:>/decodeIfPresent<T:> in synthesized Decodable
// conformances, so every model gets the tolerance without per-model churn.
extension KeyedDecodingContainer {
    private static var posix: Locale { Locale(identifier: "en_US_POSIX") }

    func decode(_ type: Decimal.Type, forKey key: Key) throws -> Decimal {
        if let string = try? decode(String.self, forKey: key) {
            guard let value = Decimal(string: string, locale: Self.posix) else {
                throw DecodingError.dataCorruptedError(
                    forKey: key,
                    in: self,
                    debugDescription: "String '\(string)' is not a decimal."
                )
            }
            return value
        }
        // Number token. Round-trip through the textual form so 4.1 stays
        // 4.1 rather than Decimal(double:)'s binary artefacts.
        let double = try decode(Double.self, forKey: key)
        return Decimal(string: "\(double)", locale: Self.posix) ?? Decimal(double)
    }

    func decodeIfPresent(_ type: Decimal.Type, forKey key: Key) throws -> Decimal? {
        guard contains(key), try decodeNil(forKey: key) == false else { return nil }
        if isEmptyObject(at: key) { return nil }
        return try decode(Decimal.self, forKey: key)
    }

    func decodeIfPresent(_ type: Int.Type, forKey key: Key) throws -> Int? {
        guard contains(key), try decodeNil(forKey: key) == false else { return nil }
        if isEmptyObject(at: key) { return nil }
        if let string = try? decode(String.self, forKey: key) {
            return Int(string)
        }
        return try decode(Int.self, forKey: key)
    }

    func decodeIfPresent(_ type: String.Type, forKey key: Key) throws -> String? {
        guard contains(key), try decodeNil(forKey: key) == false else { return nil }
        if isEmptyObject(at: key) { return nil }
        return try decode(String.self, forKey: key)
    }

    // A `{}` in place of a scalar is a serialiser-level null.
    private func isEmptyObject(at key: Key) -> Bool {
        guard let nested = try? nestedContainer(keyedBy: AnyDecodingKey.self, forKey: key) else {
            return false
        }
        return nested.allKeys.isEmpty
    }
}

private struct AnyDecodingKey: CodingKey {
    var stringValue: String
    var intValue: Int?

    init?(stringValue: String) {
        self.stringValue = stringValue
    }

    init?(intValue: Int) {
        self.intValue = intValue
        stringValue = String(intValue)
    }
}
