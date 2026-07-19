import Foundation

enum PushAuthorizationStatus: Sendable, Equatable {
    case notDetermined
    case denied
    case provisional
    case authorized

    var permitsRegistration: Bool {
        self == .authorized || self == .provisional
    }
}

enum PushPreferenceKey: String, CaseIterable, Codable, Sendable {
    case policyRenewals = "policy_renewals"
    case goalMilestones = "goal_milestones"
    case contributionReminders = "contribution_reminders"
    case marketUpdates = "market_updates"
    case fynDailyInsight = "fyn_daily_insight"
    case securityAlerts = "security_alerts"
    case paymentAlerts = "payment_alerts"
    case mortgageRateAlerts = "mortgage_rate_alerts"
    case estateAlerts = "estate_alerts"

    var title: String {
        switch self {
        case .policyRenewals: "Policy renewals"
        case .goalMilestones: "Goal milestones"
        case .contributionReminders: "Contribution reminders"
        case .marketUpdates: "Market updates"
        case .fynDailyInsight: "Daily Fyn insight"
        case .securityAlerts: "Security alerts"
        case .paymentAlerts: "Payment alerts"
        case .mortgageRateAlerts: "Mortgage rate alerts"
        case .estateAlerts: "Estate planning alerts"
        }
    }
}

struct PushPreferences: Decodable, Sendable, Equatable {
    var policyRenewals: Bool
    var goalMilestones: Bool
    var contributionReminders: Bool
    var marketUpdates: Bool
    var fynDailyInsight: Bool
    var securityAlerts: Bool
    var paymentAlerts: Bool
    var mortgageRateAlerts: Bool
    var estateAlerts: Bool
    let lifecycleChurnedSubscriber: Bool
    let lifecycleLapsedSubscriber: Bool

    static let `defaults` = Self(
        policyRenewals: true,
        goalMilestones: true,
        contributionReminders: true,
        marketUpdates: false,
        fynDailyInsight: true,
        securityAlerts: true,
        paymentAlerts: true,
        mortgageRateAlerts: true,
        estateAlerts: true,
        lifecycleChurnedSubscriber: true,
        lifecycleLapsedSubscriber: true
    )

    subscript(key: PushPreferenceKey) -> Bool {
        get {
            switch key {
            case .policyRenewals: policyRenewals
            case .goalMilestones: goalMilestones
            case .contributionReminders: contributionReminders
            case .marketUpdates: marketUpdates
            case .fynDailyInsight: fynDailyInsight
            case .securityAlerts: securityAlerts
            case .paymentAlerts: paymentAlerts
            case .mortgageRateAlerts: mortgageRateAlerts
            case .estateAlerts: estateAlerts
            }
        }
        set {
            switch key {
            case .policyRenewals: policyRenewals = newValue
            case .goalMilestones: goalMilestones = newValue
            case .contributionReminders: contributionReminders = newValue
            case .marketUpdates: marketUpdates = newValue
            case .fynDailyInsight: fynDailyInsight = newValue
            case .securityAlerts: securityAlerts = newValue
            case .paymentAlerts: paymentAlerts = newValue
            case .mortgageRateAlerts: mortgageRateAlerts = newValue
            case .estateAlerts: estateAlerts = newValue
            }
        }
    }

    private enum CodingKeys: String, CodingKey {
        case policyRenewals = "policy_renewals"
        case goalMilestones = "goal_milestones"
        case contributionReminders = "contribution_reminders"
        case marketUpdates = "market_updates"
        case fynDailyInsight = "fyn_daily_insight"
        case securityAlerts = "security_alerts"
        case paymentAlerts = "payment_alerts"
        case mortgageRateAlerts = "mortgage_rate_alerts"
        case estateAlerts = "estate_alerts"
        case lifecycleChurnedSubscriber = "lifecycle_churned_subscriber"
        case lifecycleLapsedSubscriber = "lifecycle_lapsed_subscriber"
    }
}

struct PushDeviceRegistration: Encodable, Sendable, Equatable {
    let token: String
    let deviceID: String
    let appVersion: String
    let osVersion: String

    private enum CodingKeys: String, CodingKey {
        case token = "device_token"
        case deviceID = "device_id"
        case platform
        case deviceName = "device_name"
        case appVersion = "app_version"
        case osVersion = "os_version"
    }

    func encode(to encoder: any Encoder) throws {
        var values = encoder.container(keyedBy: CodingKeys.self)
        try values.encode(token, forKey: .token)
        try values.encode(deviceID, forKey: .deviceID)
        try values.encode(appVersion, forKey: .appVersion)
        try values.encode(osVersion, forKey: .osVersion)
        try values.encode("ios", forKey: .platform)
        try values.encode("iPhone", forKey: .deviceName)
    }
}

struct PushDeviceMetadata: Sendable, Equatable {
    let appVersion: String
    let osVersion: String
}
