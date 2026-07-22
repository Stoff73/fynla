import Foundation
import Testing
@testable import Fynla

@Suite
struct AppEnvironmentTests {
    @Test
    func loadsStagingValues() throws {
        let environment = try AppEnvironment.values(stagingValues)

        #expect(environment.name == .staging)
        #expect(environment.apiBaseURL.absoluteString == "https://csjones.co/fynla")
        #expect(environment.webBaseURL.absoluteString == "https://csjones.co/fynla")
        #expect(environment.clientName == "ios")
        #expect(environment.productIdentifiers == [
            "org.fynla.premium.monthly",
            "org.fynla.premium.annual",
        ])
    }

    @Test
    func loadsProductionValues() throws {
        var values = stagingValues
        values["FYNLA_ENVIRONMENT"] = "production"
        values["FYNLA_API_BASE_URL"] = "https://fynla.org"
        values["FYNLA_WEB_BASE_URL"] = "https://fynla.org"

        let environment = try AppEnvironment.values(values)

        #expect(environment.name == .production)
        #expect(environment.apiBaseURL.absoluteString == "https://fynla.org")
        #expect(environment.webBaseURL.absoluteString == "https://fynla.org")
    }

    @Test
    func loadsValuesFromBundle() throws {
        let bundleURL = FileManager.default.temporaryDirectory
            .appending(path: "Fynla-AppEnvironmentTests-\(UUID().uuidString).bundle")
        try FileManager.default.createDirectory(at: bundleURL, withIntermediateDirectories: true)
        defer { try? FileManager.default.removeItem(at: bundleURL) }

        var info = stagingValues
        info["CFBundleIdentifier"] = "org.fynla.environment-tests"
        info["CFBundleName"] = "FynlaEnvironmentTests"
        info["CFBundleVersion"] = "1"
        info["CFBundlePackageType"] = "BNDL"

        let plist = try PropertyListSerialization.data(
            fromPropertyList: info,
            format: .xml,
            options: 0
        )
        try plist.write(to: bundleURL.appending(path: "Info.plist"))

        let bundle = try #require(Bundle(url: bundleURL))
        let environment = try AppEnvironment.bundle(bundle)

        #expect(environment.name == .staging)
        #expect(environment.apiBaseURL.absoluteString == "https://csjones.co/fynla")
    }

    @Test
    func rejectsMissingConfiguration() {
        var values = stagingValues
        values.removeValue(forKey: "FYNLA_API_BASE_URL")

        expectError(.missingValue("FYNLA_API_BASE_URL"), values: values)
    }

    @Test
    func rejectsUnknownEnvironment() {
        var values = stagingValues
        values["FYNLA_ENVIRONMENT"] = "preview"

        expectError(.invalidEnvironment("preview"), values: values)
    }

    @Test
    func rejectsInvalidURL() {
        var values = stagingValues
        values["FYNLA_API_BASE_URL"] = "not a URL"

        expectError(.invalidURL("FYNLA_API_BASE_URL"), values: values)
    }

    @Test
    func rejectsNonHTTPSURL() {
        var values = stagingValues
        values["FYNLA_API_BASE_URL"] = "http://csjones.co/fynla"

        expectError(.nonHTTPSURL("FYNLA_API_BASE_URL"), values: values)
    }

    @Test
    func rejectsUserInfoBearingURL() {
        var values = stagingValues
        values["FYNLA_WEB_BASE_URL"] = "https://user:password@csjones.co/fynla"

        expectError(.userInfoURL("FYNLA_WEB_BASE_URL"), values: values)
    }

    private var stagingValues: [String: Any] {
        [
            "FYNLA_ENVIRONMENT": "staging",
            "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
            "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
        ]
    }

    private func expectError(
        _ expected: ConfigurationError,
        values: [String: Any]
    ) {
        do {
            _ = try AppEnvironment.values(values)
            Issue.record("Expected configuration error: \(expected)")
        } catch let error as ConfigurationError {
            #expect(error == expected)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }
}
