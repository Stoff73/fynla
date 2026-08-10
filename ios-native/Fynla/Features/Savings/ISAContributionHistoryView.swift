import SwiftUI

enum ISAContributionAccountKind {
    case all
    case savings
    case investment
}

/// Displays the canonical tax-year contribution ledger shared by Cash ISA
/// and Stocks & Shares ISA surfaces.
struct ISAContributionHistoryView: View {
    let allowance: SavingsISAAllowance
    var accountID: Int?
    var accountKind: ISAContributionAccountKind = .all
    var isLoading = false
    let onSelectTaxYear: (String) -> Void

    private var rows: [SavingsISAAccountBreakdown] {
        allowance.accountBreakdown.filter { row in
            let matchesID = accountID.map { row.accountID == $0 } ?? true
            let matchesKind: Bool
            switch accountKind {
            case .all: matchesKind = true
            case .savings: matchesKind = row.isSavingsAccount
            case .investment: matchesKind = row.isInvestmentAccount
            }
            return matchesID && matchesKind
        }
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Contribution history".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)

            taxYearSelector

            if isLoading {
                HStack(spacing: 8) {
                    ProgressView()
                    Text("Loading recorded contributions…")
                }
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            if rows.isEmpty, !isLoading {
                Text("No recorded ISA contributions are available for this account and tax year.")
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            } else {
                ForEach(Array(rows.enumerated()), id: \.element.id) { index, row in
                    accountRow(row)
                        .padding(.vertical, 8)
                        .overlay(alignment: .bottom) {
                            if index < rows.count - 1 {
                                FynlaColor.Token.horizon100.color.frame(height: 1)
                            }
                        }
                }
            }
        }
        .accessibilityIdentifier("isa-contribution-history")
    }

    @ViewBuilder
    private var taxYearSelector: some View {
        let years = allowance.availableTaxYears.isEmpty
            ? [allowance.taxYear].compactMap { $0 }
            : allowance.availableTaxYears

        if years.count > 1 {
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(years, id: \.self) { taxYear in
                        Button {
                            onSelectTaxYear(taxYear)
                        } label: {
                            Text(taxYear)
                                .font(.system(size: 12, weight: .bold))
                                .foregroundStyle(
                                    taxYear == allowance.taxYear
                                        ? Color.white
                                        : FynlaColor.Token.horizon500.color
                                )
                                .padding(.horizontal, 12)
                                .padding(.vertical, 7)
                                .background(
                                    taxYear == allowance.taxYear
                                        ? FynlaColor.Token.violet500.color
                                        : FynlaColor.Token.horizon100.color
                                )
                                .clipShape(Capsule())
                        }
                        .buttonStyle(.plain)
                        .disabled(isLoading || taxYear == allowance.taxYear)
                        .accessibilityIdentifier("isa-tax-year.\(taxYear)")
                    }
                }
            }
        } else if let taxYear = allowance.taxYear ?? allowance.currentTaxYear {
            Text("Tax year \(taxYear)")
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
        }
    }

    private func accountRow(_ row: SavingsISAAccountBreakdown) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(row.accountName)
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text("\(row.isaTypeLabel) · \(row.owner.displayName)")
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                Spacer(minLength: 8)
                Text(MoneyFormatter.gbpWhole(row.contributed))
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }

            Text(row.provenanceLabel)
                .font(.system(size: 11))
                .foregroundStyle(FynlaColor.Token.neutral500.color)

            ForEach(row.contributions, id: \.stableID) { contribution in
                HStack(alignment: .firstTextBaseline, spacing: 12) {
                    Text(contributionLabel(contribution))
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                    Spacer(minLength: 8)
                    Text(MoneyFormatter.gbpWhole(contribution.amount))
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                }
            }
        }
        .accessibilityIdentifier("isa-contribution-account.\(row.accountID)")
    }

    private func contributionLabel(_ contribution: SavingsISAContribution) -> String {
        let type = contribution.entryType?
            .replacingOccurrences(of: "_", with: " ")
            .capitalized
            ?? "Recorded contribution"
        guard let date = contribution.date, !date.isEmpty else { return type }
        return "\(type) · \(date)"
    }
}
