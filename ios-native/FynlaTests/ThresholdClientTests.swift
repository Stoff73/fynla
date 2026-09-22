import Foundation
import Testing
@testable import Fynla

@Suite("Threshold client")
struct ThresholdClientTests {
    @Test
    func loadsTheStripFromTheSharedEndpoint() async throws {
        let body = try Data(contentsOf: URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent().appending(path: "Fixtures/Thresholds/taper.json"))
        let transport = TestHTTPTransport([.response(status: 200, body: body)])
        let client = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0", build: "12", transport: transport,
            tokenProvider: ThresholdTokenProvider(), requestID: { "threshold-request" }
        )

        let position = try await LiveThresholdClient(apiClient: client).load()

        #expect(position.strip == "pa_taper")
        #expect(position.lines.first?.headline == "You are £12,400 into the 60% band")
        #expect(position.lines.first?.costTotal == 12620)
        let request = try #require(await transport.requests().first)
        #expect(request.url?.path == "/fynla/api/thresholds")
    }

    @Test
    func theStripLineIsTheOneTheBackendNamed() throws {
        let position = ThresholdPosition(
            strip: "hicbc",
            lines: [
                ThresholdLine(
                    key: "pa_taper",
                    title: "Personal Allowance taper",
                    headline: "You are £12,400 into the 60% band",
                    body: "The next £12,400 you earn costs 60p in the pound.",
                    costTotal: 12620,
                    lever: nil
                ),
                ThresholdLine(
                    key: "hicbc",
                    title: "High Income Child Benefit Charge",
                    headline: "You are £3,000 over the charge",
                    body: "Some of your Child Benefit is being repaid.",
                    costTotal: 1094,
                    lever: nil
                ),
            ]
        )

        #expect(position.stripLine?.key == "hicbc")
        #expect(ThresholdPosition(strip: nil, lines: position.lines).stripLine == nil)
    }
}

private struct ThresholdTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "threshold-token" }
}
