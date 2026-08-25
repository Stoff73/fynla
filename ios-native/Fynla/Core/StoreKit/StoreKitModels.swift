import Foundation

enum StoreProductIdentifier {
    static let monthly = "org.fynla.premium.monthly"
    static let annual = "org.fynla.premium.annual"

    static let all: Set<String> = [monthly, annual]
}

struct StoreSubscriptionPeriod: Sendable, Equatable {
    enum Unit: Sendable, Equatable {
        case day
        case week
        case month
        case year
    }

    let value: Int
    let unit: Unit

    func formatted(locale: Locale) -> String {
        let formatter = DateComponentsFormatter()
        formatter.allowedUnits = [calendarUnit]
        formatter.unitsStyle = .full
        formatter.maximumUnitCount = 1
        var calendar = Calendar(identifier: .gregorian)
        calendar.locale = locale
        formatter.calendar = calendar
        return formatter.string(from: dateComponents)
            ?? value.formatted(.number.locale(locale))
    }

    private var calendarUnit: NSCalendar.Unit {
        switch unit {
        case .day: .day
        case .week: .weekOfMonth
        case .month: .month
        case .year: .year
        }
    }

    private var dateComponents: DateComponents {
        switch unit {
        case .day: DateComponents(day: value)
        case .week: DateComponents(weekOfMonth: value)
        case .month: DateComponents(month: value)
        case .year: DateComponents(year: value)
        }
    }
}

struct StoreProduct: Sendable, Equatable {
    let id: String
    let displayName: String
    let description: String
    let displayPrice: String
    let subscriptionPeriod: StoreSubscriptionPeriod

    func periodLabel(locale: Locale) -> String {
        subscriptionPeriod.formatted(locale: locale)
    }
}

struct SignedStoreTransaction: Sendable, Equatable {
    let id: UInt64
    let originalID: UInt64
    let productID: String
    let appAccountToken: UUID
    let purchaseDate: Date
    let expirationDate: Date?
    let revocationDate: Date?
    let signedJWS: String

    private let finishAction: @Sendable () async -> Void

    init(
        id: UInt64,
        originalID: UInt64,
        productID: String,
        appAccountToken: UUID,
        purchaseDate: Date,
        expirationDate: Date?,
        revocationDate: Date?,
        signedJWS: String,
        finish: @escaping @Sendable () async -> Void
    ) {
        self.id = id
        self.originalID = originalID
        self.productID = productID
        self.appAccountToken = appAccountToken
        self.purchaseDate = purchaseDate
        self.expirationDate = expirationDate
        self.revocationDate = revocationDate
        self.signedJWS = signedJWS
        finishAction = finish
    }

    func finish() async {
        await finishAction()
    }

    static func == (
        lhs: SignedStoreTransaction,
        rhs: SignedStoreTransaction
    ) -> Bool {
        lhs.id == rhs.id
            && lhs.originalID == rhs.originalID
            && lhs.productID == rhs.productID
            && lhs.appAccountToken == rhs.appAccountToken
            && lhs.purchaseDate == rhs.purchaseDate
            && lhs.expirationDate == rhs.expirationDate
            && lhs.revocationDate == rhs.revocationDate
            && lhs.signedJWS == rhs.signedJWS
    }
}

extension SignedStoreTransaction {
    static func testing(
        id: UInt64,
        originalID: UInt64,
        productID: String,
        appAccountToken: UUID,
        purchaseDate: Date = Date(timeIntervalSince1970: 1_700_000_000),
        expirationDate: Date? = nil,
        revocationDate: Date? = nil,
        signedJWS: String,
        finish: @escaping @Sendable () async -> Void = {}
    ) -> Self {
        Self(
            id: id,
            originalID: originalID,
            productID: productID,
            appAccountToken: appAccountToken,
            purchaseDate: purchaseDate,
            expirationDate: expirationDate,
            revocationDate: revocationDate,
            signedJWS: signedJWS,
            finish: finish
        )
    }
}

enum StoreKitClientError: Error, Sendable, Equatable {
    case unexpectedProduct
    case productUnavailable
    case missingSubscriptionMetadata
    case unverifiedTransaction
    case appAccountTokenMismatch
    case unsupportedPurchaseResult

    var safeMessage: String {
        switch self {
        case .unexpectedProduct, .productUnavailable, .missingSubscriptionMetadata:
            "This subscription is unavailable."
        case .unverifiedTransaction, .appAccountTokenMismatch:
            "We couldn't verify this purchase with the App Store. Please try again."
        case .unsupportedPurchaseResult:
            "The App Store couldn't complete this purchase. Please try again."
        }
    }
}

enum PurchaseOutcome: Sendable, Equatable {
    case verified(SignedStoreTransaction)
    case pending
    case userCancelled
}
