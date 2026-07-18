import Foundation
import SwiftUI

typealias AppClock = @Sendable () -> Date
typealias RequestIDFactory = @Sendable () -> String
typealias APIClientFactory = @Sendable (APIClientConfiguration) -> APIClient

struct APIClientConfiguration: Sendable {
    let environment: AppEnvironment
    let appVersion: String
    let appBuild: String
    let httpTransport: any HTTPTransport
    let accessTokenProvider: any AccessTokenProviding
    let tokenRefresher: (any AccessTokenRefreshing)?
    let requestID: RequestIDFactory
    let clock: AppClock
}

struct FeatureClients: Sendable {
    let apiClient: APIClientFactory

    static let foundation = Self { configuration in
        APIClient(
            environment: configuration.environment,
            version: configuration.appVersion,
            build: configuration.appBuild,
            transport: configuration.httpTransport,
            tokenProvider: configuration.accessTokenProvider,
            tokenRefresher: configuration.tokenRefresher,
            requestID: configuration.requestID,
            now: configuration.clock
        )
    }
}

struct AppDependencies: Sendable {
    let environment: AppEnvironment
    let httpTransport: any HTTPTransport
    let diagnostics: any DiagnosticsClient
    let accessTokenProvider: any AccessTokenProviding
    let tokenRefresher: (any AccessTokenRefreshing)?
    let clock: AppClock
    let requestID: RequestIDFactory
    let featureClients: FeatureClients

    private let appVersion: String
    private let appBuild: String

    init(
        environment: AppEnvironment,
        appVersion: String,
        appBuild: String,
        httpTransport: any HTTPTransport,
        diagnostics: any DiagnosticsClient,
        accessTokenProvider: any AccessTokenProviding,
        tokenRefresher: (any AccessTokenRefreshing)? = nil,
        clock: @escaping AppClock,
        requestID: @escaping RequestIDFactory,
        featureClients: FeatureClients
    ) {
        self.environment = environment
        self.appVersion = Self.normalizedVersion(appVersion)
        self.appBuild = appBuild
        self.httpTransport = httpTransport
        self.diagnostics = diagnostics
        self.accessTokenProvider = accessTokenProvider
        self.tokenRefresher = tokenRefresher
        self.clock = clock
        self.requestID = requestID
        self.featureClients = featureClients
    }

    static func live(bundle: Bundle = .main) throws -> Self {
        Self(
            environment: try AppEnvironment.bundle(bundle),
            appVersion: try requiredBundleValue(
                "CFBundleShortVersionString",
                bundle: bundle
            ),
            appBuild: try requiredBundleValue("CFBundleVersion", bundle: bundle),
            httpTransport: URLSessionHTTPTransport.ephemeral(),
            diagnostics: RedactingDiagnosticsClient.live(),
            accessTokenProvider: UnavailableAccessTokenProvider(),
            clock: { Date() },
            requestID: { UUID().uuidString },
            featureClients: .foundation
        )
    }

    func makeAPIClient(
        tokenRefresher: (any AccessTokenRefreshing)? = nil
    ) -> APIClient {
        featureClients.apiClient(
            APIClientConfiguration(
                environment: environment,
                appVersion: appVersion,
                appBuild: appBuild,
                httpTransport: httpTransport,
                accessTokenProvider: accessTokenProvider,
                tokenRefresher: tokenRefresher ?? self.tokenRefresher,
                requestID: requestID,
                clock: clock
            )
        )
    }

    func makeAuthenticationClient() -> APIAuthClient {
        APIAuthClient(
            environment: environment,
            version: appVersion,
            build: appBuild,
            transport: httpTransport,
            requestID: requestID
        )
    }

    func makeFynClient() -> LiveFynClient {
        LiveFynClient(
            apiClient: makeAPIClient(),
            environment: environment,
            version: appVersion,
            build: appBuild,
            transport: httpTransport,
            tokenProvider: accessTokenProvider,
            requestID: requestID
        )
    }

    func makeBugReportMetadata(
        route: String,
        nativeSessionUUID: String
    ) -> BugReportMetadata {
        BugReportMetadata(
            appVersion: appVersion,
            appBuild: appBuild,
            environment: environment.name.rawValue,
            route: route,
            requestCorrelationID: nil,
            nativeSessionUUID: nativeSessionUUID
        )
    }

    func authenticatedSession(
        accessTokenProvider: any AccessTokenProviding,
        tokenRefresher: any AccessTokenRefreshing
    ) -> Self {
        Self(
            environment: environment,
            appVersion: appVersion,
            appBuild: appBuild,
            httpTransport: httpTransport,
            diagnostics: diagnostics,
            accessTokenProvider: accessTokenProvider,
            tokenRefresher: tokenRefresher,
            clock: clock,
            requestID: requestID,
            featureClients: featureClients
        )
    }

    private static func requiredBundleValue(
        _ key: String,
        bundle: Bundle
    ) throws -> String {
        guard let value = bundle.object(forInfoDictionaryKey: key) as? String,
              !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        else {
            throw ConfigurationError.missingValue(key)
        }

        return value
    }

    private static func normalizedVersion(_ value: String) -> String {
        switch value.split(separator: ".", omittingEmptySubsequences: false).count {
        case 1:
            "\(value).0.0"
        case 2:
            "\(value).0"
        default:
            value
        }
    }
}

private struct UnavailableAccessTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? {
        nil
    }
}

private struct AppDependenciesEnvironmentKey: EnvironmentKey {
    static let defaultValue: AppDependencies? = nil
}

extension EnvironmentValues {
    var appDependencies: AppDependencies? {
        get { self[AppDependenciesEnvironmentKey.self] }
        set { self[AppDependenciesEnvironmentKey.self] = newValue }
    }
}
