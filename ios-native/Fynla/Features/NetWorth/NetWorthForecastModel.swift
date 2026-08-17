import Foundation
import Observation

@MainActor
@Observable
final class NetWorthForecastModel {
    private(set) var state: NetWorthForecastViewState = .idle
    private(set) var basis: NetWorthForecastBasis = .nominal
    private(set) var validationErrors: [NetWorthForecastCategory: String] = [:]
    private(set) var feedback: String?
    private(set) var saveError: String?
    private let client: any NetWorthForecastClient
    private var lastForecast: NetWorthForecast?
    private var editValues: [NetWorthForecastCategory: String] = [:]
    private var generation = 0

    init(client: any NetWorthForecastClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastForecast
        if previous == nil {
            state = .loading
        }

        do {
            let forecast = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastForecast = forecast
            applyEditValues(from: forecast)
            state = .loaded(forecast)
        } catch is CancellationError {
            guard activeGeneration == generation, let previous else { return }
            state = .loaded(previous)
        } catch let error as APIError {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            map(error, previous: previous)
        } catch {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            state = .failed(requestID: nil)
        }
    }

    func refresh() async {
        await load()
    }

    func editValue(for category: NetWorthForecastCategory) -> String {
        editValues[category] ?? ""
    }

    func setEditValue(_ value: String, for category: NetWorthForecastCategory) {
        editValues[category] = value
        validationErrors[category] = nil
        feedback = nil
        saveError = nil
    }

    func setBasis(_ basis: NetWorthForecastBasis) {
        self.basis = basis
        feedback = nil
        saveError = nil
    }

    func save() async {
        guard let previous = lastForecast,
              let update = validatedUpdate()
        else { return }

        state = .saving(previous: previous)
        feedback = nil
        saveError = nil

        do {
            let assumptions = try await client.updateAssumptions(update)
            await refreshAfterMutation(
                previous: previous,
                assumptions: assumptions,
                success: "Assumptions saved."
            )
        } catch is CancellationError {
            state = .loaded(previous)
        } catch let error as APIError {
            mapMutation(error, previous: previous)
        } catch {
            state = .loaded(previous)
            saveError = "We could not save your assumptions. Please try again."
        }
    }

    func reset() async {
        guard let previous = lastForecast else { return }
        state = .saving(previous: previous)
        feedback = nil
        saveError = nil
        validationErrors = [:]

        do {
            let assumptions = try await client.resetAssumptions()
            await refreshAfterMutation(
                previous: previous,
                assumptions: assumptions,
                success: "Assumptions reset to Fynla defaults."
            )
        } catch is CancellationError {
            state = .loaded(previous)
        } catch let error as APIError {
            mapMutation(error, previous: previous)
        } catch {
            state = .loaded(previous)
            saveError = "We could not reset your assumptions. Please try again."
        }
    }

    func stop() {
        generation &+= 1
        lastForecast = nil
        editValues = [:]
        validationErrors = [:]
        feedback = nil
        saveError = nil
        basis = .nominal
        state = .idle
    }

    private func applyEditValues(from forecast: NetWorthForecast) {
        editValues = Dictionary(uniqueKeysWithValues: NetWorthForecastCategory.allCases.map {
            ($0, decimalString(forecast.assumptions[$0].ratePercent))
        })
        let overridden = NetWorthForecastCategory.allCases.first {
            forecast.assumptions[$0].source == .userOverride
        }
        basis = forecast.assumptions[overridden ?? .property].basis
        validationErrors = [:]
    }

    private func validatedUpdate() -> NetWorthForecastAssumptionUpdate? {
        validationErrors = [:]
        var rates: [NetWorthForecastCategory: Decimal] = [:]
        let locale = Locale(identifier: "en_US_POSIX")

        for category in NetWorthForecastCategory.allCases {
            let value = editValues[category] ?? ""
            guard let rate = Decimal(string: value, locale: locale),
                  rate >= -20,
                  rate <= 30
            else {
                validationErrors[category] = "Enter a percentage from -20 to 30."
                continue
            }
            rates[category] = rate
        }

        guard validationErrors.isEmpty else {
            saveError = "Check the highlighted assumptions."
            return nil
        }

        return NetWorthForecastAssumptionUpdate(rates: rates, basis: basis)
    }

    private func decimalString(_ value: Decimal) -> String {
        NSDecimalNumber(decimal: value).stringValue
    }

    private func refreshAfterMutation(
        previous: NetWorthForecast,
        assumptions: NetWorthForecastAssumptions,
        success: String
    ) async {
        let reconciled = NetWorthForecast(
            contractVersion: previous.contractVersion,
            recordedAsOf: previous.recordedAsOf,
            current: previous.current,
            points: previous.points,
            assumptions: assumptions,
            warnings: previous.warnings
        )
        lastForecast = reconciled
        applyEditValues(from: reconciled)
        state = .loaded(reconciled)
        feedback = success

        do {
            let refreshed = try await client.load()
            lastForecast = refreshed
            applyEditValues(from: refreshed)
            state = .loaded(refreshed)
            feedback = success
        } catch is CancellationError {
            state = .loaded(reconciled)
        } catch APIError.offline {
            state = .offline(previous: reconciled)
            feedback = "\(success) The projection will refresh when you're online."
        } catch {
            state = .loaded(reconciled)
            saveError = "Your assumptions were saved, but the projection could not refresh."
        }
    }

    private func map(_ error: APIError, previous: NetWorthForecast?) {
        switch error {
        case .offline:
            state = .offline(previous: previous)
        case .unauthenticated:
            state = .unauthenticated
        case let .upgradeRequired(message):
            state = .upgradeRequired(message: message)
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation, .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            state = .failed(requestID: nil)
        }
    }

    private func mapMutation(_ error: APIError, previous: NetWorthForecast) {
        switch error {
        case let .validation(errors):
            for (key, messages) in errors {
                guard let category = NetWorthForecastCategory(rawValue: key),
                      let message = messages.first
                else { continue }
                validationErrors[category] = message
            }
            state = .loaded(previous)
            saveError = "Check the highlighted assumptions."
        case .offline:
            state = .offline(previous: previous)
            saveError = "You're offline. Your assumptions were not changed."
        case .unauthenticated:
            state = .unauthenticated
        case let .upgradeRequired(message):
            state = .upgradeRequired(message: message)
        case let .server(_, requestID), let .decoding(requestID):
            state = .loaded(previous)
            saveError = requestID.map {
                "We could not save your assumptions. Request ID: \($0)"
            } ?? "We could not save your assumptions. Please try again."
        case .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            state = .loaded(previous)
            saveError = "We could not save your assumptions. Please try again."
        }
    }
}
