import Foundation
import Testing
@testable import Fynla

@Suite("Tolerant decoding")
struct TolerantDecodingTests {
    private struct Sample: Decodable, Equatable {
        let amount: Decimal
        let rate: Decimal?
        let days: Int?
        let name: String?
    }

    @Test
    func decodesLaravelStringNumericsAndEmptyObjectNulls() throws {
        let json = #"{"amount":"40000.00","rate":"4.1000","days":{},"name":{}}"#
        let value = try JSONDecoder().decode(Sample.self, from: Data(json.utf8))
        #expect(value.amount == Decimal(string: "40000.00"))
        #expect(value.rate == Decimal(string: "4.1000"))
        #expect(value.days == nil)
        #expect(value.name == nil)
    }

    @Test
    func numberTokensAndPlainValuesStillDecode() throws {
        let json = #"{"amount":8000,"rate":4.1,"days":90,"name":"Barclays"}"#
        let value = try JSONDecoder().decode(Sample.self, from: Data(json.utf8))
        #expect(value.amount == 8000)
        #expect(value.rate == Decimal(string: "4.1"))
        #expect(value.days == 90)
        #expect(value.name == "Barclays")
    }

    @Test
    func nullsAndAbsentKeysStayNil() throws {
        let json = #"{"amount":"1.50","rate":null}"#
        let value = try JSONDecoder().decode(Sample.self, from: Data(json.utf8))
        #expect(value.rate == nil)
        #expect(value.days == nil)
        #expect(value.name == nil)
    }
}
