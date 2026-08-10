import Foundation

protocol NetWorthForecastClient: Sendable {
    func load() async throws -> NetWorthForecast
    func updateAssumptions(
        _ update: NetWorthForecastAssumptionUpdate
    ) async throws -> NetWorthForecastAssumptions
    func resetAssumptions() async throws -> NetWorthForecastAssumptions
}

struct LiveNetWorthForecastClient: NetWorthForecastClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> NetWorthForecast {
        try await apiClient.send(
            APIRequest<NetWorthForecast>(
                path: "api/net-worth/forecast",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func updateAssumptions(
        _ update: NetWorthForecastAssumptionUpdate
    ) async throws -> NetWorthForecastAssumptions {
        let encoder = JSONEncoder()
        encoder.outputFormatting = .sortedKeys
        return try await apiClient.send(
            APIRequest<NetWorthForecastAssumptions>(
                path: "api/net-worth/forecast/assumptions",
                method: .put,
                body: try encoder.encode(update)
            )
        )
    }

    func resetAssumptions() async throws -> NetWorthForecastAssumptions {
        try await apiClient.send(
            APIRequest<NetWorthForecastAssumptions>(
                path: "api/net-worth/forecast/assumptions",
                method: .delete
            )
        )
    }
}
