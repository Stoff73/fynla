import Foundation
import Testing
@testable import Fynla

@Suite("Net worth forecast feature")
struct NetWorthForecastTests {
    @Test
    func decodesTheCanonicalForecastAndCategoryAssumptions() throws {
        let forecast = try JSONDecoder().decode(
            NetWorthForecast.self,
            from: Data(Self.forecastJSON.utf8)
        )

        #expect(forecast.contractVersion == "net_worth_forecast_v1")
        #expect(forecast.recordedAsOf == "2026-08-10")
        #expect(forecast.current.netWorth == Decimal(350_000))
        #expect(forecast.points.map(\.source) == [.recorded, .projected])
        #expect(forecast.points.last?.netWorth == Decimal(371_200))
        #expect(forecast.assumptions[.property].ratePercent == Decimal(string: "3"))
        #expect(forecast.assumptions[.property].source == .systemDefault)
        #expect(forecast.assumptions[.property].effectiveFrom == "2026-08-10")
        #expect(forecast.assumptions[.property].basis == .nominal)
        #expect(forecast.assumptions[.otherLiabilities].ratePercent == 0)
    }

    @Test
    func clientUsesTheExactForecastAndAssumptionEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: Data(Self.forecastEnvelopeJSON.utf8)),
            .response(status: 200, body: Data(Self.assumptionsEnvelopeJSON.utf8)),
            .response(status: 200, body: Data(Self.assumptionsEnvelopeJSON.utf8)),
        ])
        let apiClient = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: NetWorthForecastTokenProvider(),
            requestID: { "forecast-request" }
        )
        let client = LiveNetWorthForecastClient(apiClient: apiClient)

        let loaded = try await client.load()
        let updated = try await client.updateAssumptions(
            NetWorthForecastAssumptionUpdate(
                rates: [.property: Decimal(string: "4.25")!],
                basis: .real
            )
        )
        let reset = try await client.resetAssumptions()

        #expect(loaded.points.last?.netWorth == Decimal(371_200))
        #expect(updated[.property].ratePercent == 3)
        #expect(reset[.cash].ratePercent == 2)

        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/net-worth/forecast",
            "/fynla/api/net-worth/forecast/assumptions",
            "/fynla/api/net-worth/forecast/assumptions",
        ])
        #expect(requests.map(\.httpMethod) == ["GET", "PUT", "DELETE"])
        #expect(requests.allSatisfy {
            $0.value(forHTTPHeaderField: "Authorization") == "Bearer forecast-token"
        })
        let body = try #require(requests[1].httpBody)
        let json = try #require(
            JSONSerialization.jsonObject(with: body) as? [String: Any]
        )
        #expect(json["property"] as? Double == 4.25)
        #expect(json["basis"] as? String == "real")
    }

    @Test @MainActor
    func modelPreservesThePreviousForecastOfflineAndKeepsCategoryEditsSeparate() async throws {
        let forecast = try JSONDecoder().decode(
            NetWorthForecast.self,
            from: Data(Self.forecastJSON.utf8)
        )
        let model = NetWorthForecastModel(
            client: NetWorthForecastClientStub([
                .success(forecast),
                .failure(APIError.offline),
            ], assumptions: forecast.assumptions)
        )

        await model.load()
        #expect(model.state == .loaded(forecast))
        #expect(model.editValue(for: .property) == "3")
        #expect(model.editValue(for: .investments) == "5")

        model.setEditValue("4.25", for: .property)
        #expect(model.editValue(for: .property) == "4.25")
        #expect(model.editValue(for: .investments) == "5")

        await model.refresh()
        #expect(model.state == .offline(previous: forecast))
        #expect(model.editValue(for: .property) == "4.25")
    }

    @Test @MainActor
    func modelSavesAllDisplayedRatesAndResetsFromTheServer() async throws {
        let forecast = try JSONDecoder().decode(
            NetWorthForecast.self,
            from: Data(Self.forecastJSON.utf8)
        )
        let client = NetWorthForecastRecordingClient(forecast: forecast)
        let model = NetWorthForecastModel(client: client)

        await model.load()
        model.setEditValue("4.25", for: .property)
        model.setBasis(.real)
        await model.save()

        let update = try #require(await client.lastUpdate())
        #expect(update.rates[.property] == Decimal(string: "4.25"))
        #expect(update.rates[.investments] == Decimal(5))
        #expect(update.basis == .real)
        #expect(model.feedback == "Assumptions saved.")

        await model.reset()
        #expect(await client.resetCount() == 1)
        #expect(model.feedback == "Assumptions reset to Fynla defaults.")
    }

    @Test @MainActor
    func modelDoesNotMisreportASuccessfulSaveWhenProjectionRefreshGoesOffline() async throws {
        let forecast = try JSONDecoder().decode(
            NetWorthForecast.self,
            from: Data(Self.forecastJSON.utf8)
        )
        let client = NetWorthForecastClientStub([
            .success(forecast),
            .failure(APIError.offline),
        ], assumptions: forecast.assumptions)
        let model = NetWorthForecastModel(client: client)

        await model.load()
        model.setEditValue("4.25", for: .property)
        await model.save()

        #expect(model.state == .offline(previous: forecast))
        #expect(
            model.feedback
                == "Assumptions saved. The projection will refresh when you're online."
        )
        #expect(model.saveError == nil)
    }

    private static let forecastJSON = #"""
    {
      "contract_version": "net_worth_forecast_v1",
      "recorded_as_of": "2026-08-10",
      "current": {
        "assets": {"property": 500000, "investments": 30000, "pensions": 0, "cash": 20000, "business": 0, "valuables": 0},
        "liabilities": {"mortgages": 200000, "other_liabilities": 0},
        "total_assets": 550000,
        "total_liabilities": 200000,
        "net_worth": 350000
      },
      "points": [
        {"year": 0, "calendar_year": 2026, "categories": {"property": 500000}, "liabilities": {"mortgages": 200000}, "total_assets": 550000, "total_liabilities": 200000, "net_worth": 350000, "source": "recorded"},
        {"year": 1, "calendar_year": 2027, "categories": {"property": 515000}, "liabilities": {"mortgages": 194000}, "total_assets": 565200, "total_liabilities": 194000, "net_worth": 371200, "source": "projected"}
      ],
      "assumptions": {
        "property": {"rate_percent": 3, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "investments": {"rate_percent": 5, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "pensions": {"rate_percent": 5, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "cash": {"rate_percent": 2, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "business": {"rate_percent": 3, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "valuables": {"rate_percent": 2, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "mortgages": {"rate_percent": 0, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
        "other_liabilities": {"rate_percent": 0, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"}
      },
      "cash_flows": {"annual_contributions": {"cash": 0, "investments": 0, "pensions": 0}, "annual_repayments": {"mortgages": 6000, "other_liabilities": 0}},
      "warnings": [],
      "methodology": {"forecast_points_written_to_recorded_history": false}
    }
    """#

    private static let forecastEnvelopeJSON = #"""
    {"success": true, "data": \#(forecastJSON)}
    """#

    private static let assumptionsEnvelopeJSON = #"""
    {"success": true, "data": {
      "property": {"rate_percent": 3, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "investments": {"rate_percent": 5, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "pensions": {"rate_percent": 5, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "cash": {"rate_percent": 2, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "business": {"rate_percent": 3, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "valuables": {"rate_percent": 2, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "mortgages": {"rate_percent": 0, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"},
      "other_liabilities": {"rate_percent": 0, "source": "system_default", "effective_from": "2026-08-10", "basis": "nominal"}
    }}
    """#
}

private struct NetWorthForecastTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "forecast-token" }
}

private actor NetWorthForecastClientStub: NetWorthForecastClient {
    private var loadResults: [Result<NetWorthForecast, Error>]
    private let assumptions: NetWorthForecastAssumptions

    init(
        _ loadResults: [Result<NetWorthForecast, Error>],
        assumptions: NetWorthForecastAssumptions
    ) {
        self.loadResults = loadResults
        self.assumptions = assumptions
    }

    func load() async throws -> NetWorthForecast {
        guard !loadResults.isEmpty else { throw APIError.offline }
        return try loadResults.removeFirst().get()
    }

    func updateAssumptions(
        _ update: NetWorthForecastAssumptionUpdate
    ) async throws -> NetWorthForecastAssumptions {
        assumptions
    }

    func resetAssumptions() async throws -> NetWorthForecastAssumptions {
        assumptions
    }
}

private actor NetWorthForecastRecordingClient: NetWorthForecastClient {
    private let forecast: NetWorthForecast
    private var update: NetWorthForecastAssumptionUpdate?
    private var resets = 0

    init(forecast: NetWorthForecast) {
        self.forecast = forecast
    }

    func load() async throws -> NetWorthForecast { forecast }

    func updateAssumptions(
        _ update: NetWorthForecastAssumptionUpdate
    ) async throws -> NetWorthForecastAssumptions {
        self.update = update
        return forecast.assumptions
    }

    func resetAssumptions() async throws -> NetWorthForecastAssumptions {
        resets += 1
        return forecast.assumptions
    }

    func lastUpdate() -> NetWorthForecastAssumptionUpdate? { update }
    func resetCount() -> Int { resets }
}
