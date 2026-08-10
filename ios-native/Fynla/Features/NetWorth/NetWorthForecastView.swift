import Charts
import SwiftUI

struct NetWorthForecastView: View {
    let model: NetWorthForecastModel

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                card {
                    heading
                    HStack(spacing: 10) {
                        ProgressView()
                        Text("Loading your projection…")
                            .font(.system(size: 14))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                    .padding(.vertical, 14)
                }
            case let .loaded(forecast):
                content(forecast)
            case let .saving(previous):
                content(previous, isSaving: true)
            case let .offline(previous):
                if let previous {
                    content(previous, offline: true)
                } else {
                    errorCard("You're offline. Connect to load your projection.")
                }
            case .unauthenticated:
                errorCard("Sign in again to load your projection.")
            case let .upgradeRequired(message):
                errorCard(message)
            case let .failed(requestID):
                errorCard(
                    requestID.map { "We could not load your projection. Request ID: \($0)" }
                        ?? "We could not load your projection."
                )
            }
        }
        .task { await model.load() }
        .accessibilityIdentifier("net-worth.forecast")
    }

    private func content(
        _ forecast: NetWorthForecast,
        offline: Bool = false,
        isSaving: Bool = false
    ) -> some View {
        card {
            heading

            if offline {
                Text("You're offline. Showing your last loaded projection.")
                    .font(.system(size: 12, weight: .semibold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .padding(10)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(FynlaColor.Token.savannah100.color)
                    .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
            }

            provenance(forecast)
            forecastChart(forecast.points)

            if let latest = forecast.points.last {
                HStack(alignment: .firstTextBaseline, spacing: 12) {
                    Text("Projected in \(latest.calendarYear)")
                        .font(.system(size: 13))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Spacer(minLength: 8)
                    Text(MoneyFormatter.gbpWhole(latest.netWorth))
                        .font(.system(size: 17, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                }
                .padding(.top, 4)
            }

            ForEach(forecast.warnings, id: \.self) { warning in
                Text(warning)
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral600.color)
                    .padding(10)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(FynlaColor.Token.savannah100.color)
                    .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
            }

            Divider().padding(.vertical, 4)
            assumptionEditor(
                forecast,
                isSaving: isSaving,
                disabled: isSaving || offline
            )
        }
    }

    private var heading: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Projected net worth".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Text("A forward view using your recorded balances, contributions and disclosed assumptions.")
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
        }
    }

    private func provenance(_ forecast: NetWorthForecast) -> some View {
        ViewThatFits(in: .horizontal) {
            HStack(spacing: 12) {
                provenanceLabels(forecast)
            }
            VStack(alignment: .leading, spacing: 3) {
                provenanceLabels(forecast)
            }
        }
        .font(.system(size: 11, weight: .semibold))
        .foregroundStyle(FynlaColor.Token.neutral500.color)
        .padding(.top, 6)
    }

    @ViewBuilder
    private func provenanceLabels(_ forecast: NetWorthForecast) -> some View {
        Text("Recorded starting point: \(displayDate(forecast.recordedAsOf))")
        if let year = forecast.points.first(where: { $0.source == .projected })?.calendarYear {
            Text("Projected from \(year)")
        }
    }

    private func forecastChart(_ points: [NetWorthForecastPoint]) -> some View {
        Chart(points) { point in
            LineMark(
                x: .value("Year", point.calendarYear),
                y: .value("Net worth", decimalDouble(point.netWorth))
            )
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .interpolationMethod(.catmullRom)
        }
        .chartXAxis {
            AxisMarks(values: .automatic(desiredCount: 4))
        }
        .chartYAxis {
            AxisMarks(position: .leading)
        }
        .frame(height: 230)
        .accessibilityLabel("Recorded starting net worth and projected yearly net worth")
    }

    private func assumptionEditor(
        _ forecast: NetWorthForecast,
        isSaving: Bool,
        disabled: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Forecast assumptions")
                .font(.system(size: 17, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Text("Percentages are applied independently to each recorded category.")
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)

            Picker(
                "Basis",
                selection: Binding(
                    get: { model.basis },
                    set: { model.setBasis($0) }
                )
            ) {
                ForEach(NetWorthForecastBasis.allCases, id: \.self) { basis in
                    Text(basis.title).tag(basis)
                }
            }
            .pickerStyle(.segmented)
            .disabled(disabled)

            ForEach(NetWorthForecastCategory.allCases) { category in
                assumptionRow(
                    category,
                    assumption: forecast.assumptions[category],
                    disabled: disabled
                )
            }

            if let feedback = model.feedback {
                Text(feedback)
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.spring600.color)
                    .accessibilityIdentifier("net-worth.forecast.feedback")
            }
            if let saveError = model.saveError {
                Text(saveError)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.raspberry600.color)
                    .accessibilityIdentifier("net-worth.forecast.error")
            }

            Button {
                Task { await model.save() }
            } label: {
                Text(isSaving ? "Saving…" : "Save assumptions")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(.white)
                    .frame(maxWidth: .infinity)
                    .padding(14)
                    .background(FynlaColor.Token.raspberry500.color)
                    .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
            }
            .buttonStyle(.plain)
            .disabled(disabled)
            .opacity(disabled ? 0.5 : 1)
            .accessibilityIdentifier("net-worth.forecast.save")

            Button("Reset to defaults") {
                Task { await model.reset() }
            }
            .font(.system(size: 13, weight: .bold))
            .foregroundStyle(FynlaColor.Token.raspberry500.color)
            .frame(maxWidth: .infinity)
            .disabled(disabled)
            .accessibilityIdentifier("net-worth.forecast.reset")
        }
        .accessibilityIdentifier("net-worth.forecast.assumptions")
    }

    private func assumptionRow(
        _ category: NetWorthForecastCategory,
        assumption: NetWorthForecastAssumption,
        disabled: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack(alignment: .center, spacing: 12) {
                Text(category.title)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer(minLength: 8)
                HStack(spacing: 4) {
                    TextField(
                        "Rate",
                        text: Binding(
                            get: { model.editValue(for: category) },
                            set: { model.setEditValue($0, for: category) }
                        )
                    )
                    .keyboardType(.numbersAndPunctuation)
                    .multilineTextAlignment(.trailing)
                    .frame(minWidth: 64, idealWidth: 72, maxWidth: 88)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 9)
                    .background(Color.white)
                    .overlay(
                        RoundedRectangle(cornerRadius: 7, style: .continuous)
                            .stroke(FynlaColor.Token.horizon300.color, lineWidth: 1)
                    )
                    .disabled(disabled)
                    .accessibilityIdentifier("net-worth.forecast.rate.\(category.rawValue)")
                    Text("%")
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.neutral600.color)
                }
            }
            Text("\(assumption.source.title) · \(assumption.effectiveFrom) · \(assumption.basis.title)")
                .font(.system(size: 10))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .fixedSize(horizontal: false, vertical: true)
            if let error = model.validationErrors[category] {
                Text(error)
                    .font(.system(size: 11))
                    .foregroundStyle(FynlaColor.Token.raspberry600.color)
            }
        }
        .padding(.vertical, 6)
        .accessibilityElement(children: .contain)
    }

    private func errorCard(_ message: String) -> some View {
        card {
            heading
            Text(message)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.raspberry600.color)
            Button("Try again") {
                Task { await model.load() }
            }
            .font(.system(size: 14, weight: .bold))
            .foregroundStyle(.white)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(FynlaColor.Token.raspberry500.color)
            .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
        }
    }

    private func card<Content: View>(
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: 12, content: content)
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func displayDate(_ date: String) -> String {
        let parser = DateFormatter()
        parser.locale = Locale(identifier: "en_US_POSIX")
        parser.timeZone = TimeZone(secondsFromGMT: 0)
        parser.dateFormat = "yyyy-MM-dd"
        guard let parsed = parser.date(from: date) else { return date }
        return MoneyFormatter.ukDate(parsed)
    }

    private func decimalDouble(_ value: Decimal) -> Double {
        NSDecimalNumber(decimal: value).doubleValue
    }
}
