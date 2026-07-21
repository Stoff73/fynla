import Foundation
import Testing
@testable import Fynla

@Suite("API contract fixtures")
struct APIFixtureTests {
    @Test
    func decodesMobileDashboardFixture() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<DashboardFixture>.self,
            from: fixture("mobile-dashboard")
        )

        #expect(envelope.success)
        #expect(envelope.data.modules["savings"]?.status == "active")
        #expect(envelope.data.netWorth.total == 125_000)
        #expect(envelope.data.level.level == 2)
        #expect(envelope.data.percentile == 64)
    }

    @Test
    func decodesAuthenticatedUserFixture() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<AuthenticatedUserFixture>.self,
            from: fixture("auth-user")
        )

        #expect(envelope.success)
        #expect(envelope.data.user.id == 101)
        #expect(envelope.data.tierFlags.resolvedTier == "free")
        #expect(envelope.data.tierFlags.capabilities["dashboard"] == "full")
        #expect(envelope.data.tierFlags.limits["savings_account"] == 2)
    }

    @Test
    func decodesSavingsModuleFixture() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<SavingsModuleFixture>.self,
            from: fixture("savings-module")
        )

        #expect(envelope.success)
        #expect(envelope.data.module == "savings")
        #expect(envelope.data.summary.totalSavings == 25_000)
        #expect(envelope.data.summary.emergencyFund.runwayMonths == 4.2)
    }

    private struct DashboardFixture: Decodable, Sendable {
        struct Module: Decodable, Sendable { let status: String }
        struct NetWorth: Decodable, Sendable { let total: Double }
        struct Level: Decodable, Sendable { let level: Int }

        let modules: [String: Module]
        let netWorth: NetWorth
        let level: Level
        let percentile: Int

        enum CodingKeys: String, CodingKey {
            case modules
            case netWorth = "net_worth"
            case level
            case percentile
        }
    }

    private struct AuthenticatedUserFixture: Decodable, Sendable {
        struct User: Decodable, Sendable { let id: Int }
        struct TierFlags: Decodable, Sendable {
            let resolvedTier: String
            let capabilities: [String: String]
            let limits: [String: Int]

            enum CodingKeys: String, CodingKey {
                case resolvedTier = "resolved_tier"
                case capabilities
                case limits
            }
        }

        let user: User
        let tierFlags: TierFlags

        enum CodingKeys: String, CodingKey {
            case user
            case tierFlags = "tier_flags"
        }
    }

    private struct SavingsModuleFixture: Decodable, Sendable {
        struct Summary: Decodable, Sendable {
            struct EmergencyFund: Decodable, Sendable {
                let runwayMonths: Double

                enum CodingKeys: String, CodingKey {
                    case runwayMonths = "runway_months"
                }
            }

            let totalSavings: Double
            let emergencyFund: EmergencyFund

            enum CodingKeys: String, CodingKey {
                case totalSavings = "total_savings"
                case emergencyFund = "emergency_fund"
            }
        }

        let module: String
        let summary: Summary
    }

    private func fixture(_ name: String) throws -> Data {
        let bundle = Bundle(for: FixtureBundleToken.self)
        if let url = bundle.url(
            forResource: name,
            withExtension: "json",
            subdirectory: "Fixtures/API"
        ) ?? bundle.url(forResource: name, withExtension: "json") {
            return try Data(contentsOf: url)
        }

        let url = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/API/\(name).json")
        return try Data(contentsOf: url)
    }
}

private final class FixtureBundleToken {}
