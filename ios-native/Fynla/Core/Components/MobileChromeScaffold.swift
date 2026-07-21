import SwiftUI

// Shared pieces of /m's MobileChrome (resources/mobile/components/
// MobileChrome.vue + dashboard.css): the fixed gradient app bar lives in the
// shell; these views give each screen the gradient page-hero band, the
// Back / "Edit details" action row and the dock-clearing bottom pad.

enum MobileChromeMetrics {
    // --grad-span (22rem) and the header's content height (2.75rem hamburger
    // + 0.875rem vertical padding), as on /m.
    static let gradientSpan: CGFloat = 352
    static let headerContentHeight: CGFloat = 72
    // md-bottom-pad: clears the docked Fyn bar.
    static let bottomClearance: CGFloat = 80

    static var sharedGradient: LinearGradient {
        LinearGradient(
            colors: [
                FynlaColor.Token.horizon500.color,
                FynlaColor.Token.raspberry500.color,
            ],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }
}

// The slice of the shared 135° gradient that continues below the fixed
// header. Attach as the background of the first scroll element: it anchors
// itself to the gradient's screen-top origin at first layout (scroll offset
// zero), so the slice lines up with the header's slice on any device.
struct MobileChromeGradientSlice: View {
    @State private var anchor: CGFloat?

    var body: some View {
        GeometryReader { geo in
            Rectangle()
                .fill(MobileChromeMetrics.sharedGradient)
                .frame(height: MobileChromeMetrics.gradientSpan)
                .offset(y: -(anchor ?? geo.frame(in: .global).minY))
                .onAppear {
                    if anchor == nil {
                        anchor = geo.frame(in: .global).minY
                    }
                }
        }
        .clipped()
        .allowsHitTesting(false)
    }
}

// md-page-hero: white 1.6rem/900 title + 0.875rem subtitle on the gradient.
struct MobilePageHero: View {
    let title: String
    var subtitle: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title)
                .font(.system(size: 26, weight: .black))
                .foregroundStyle(.white)
            if let subtitle, !subtitle.isEmpty {
                Text(subtitle)
                    .font(.system(size: 14))
                    .foregroundStyle(.white.opacity(0.82))
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .padding(.bottom, 24)
        .background { MobileChromeGradientSlice() }
        .accessibilityElement(children: .combine)
        .accessibilityAddTraits(.isHeader)
    }
}

// /m's m-hero card: dark horizon-500 card with a light label, white 44pt/900
// metric (optional suffix like "a year"), light sub-line and an optional
// extra slot (e.g. Retirement's target/surplus split).
struct MobileHeroCard<Extra: View>: View {
    let label: String
    let metric: String
    var metricSuffix: String?
    var sub: String?
    // /m occasionally tints the metric (e.g. Tax Strategy's .mts-available
    // headroom count in spring-600); default stays the white m-metric.
    var metricColor: Color
    @ViewBuilder var extra: Extra

    init(
        label: String,
        metric: String,
        metricSuffix: String? = nil,
        sub: String? = nil,
        metricColor: Color = .white,
        @ViewBuilder extra: () -> Extra = { EmptyView() }
    ) {
        self.label = label
        self.metric = metric
        self.metricSuffix = metricSuffix
        self.sub = sub
        self.metricColor = metricColor
        self.extra = extra()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label)
                .font(.system(size: 14))
                .foregroundStyle(.white.opacity(0.78))
            HStack(alignment: .firstTextBaseline, spacing: 6) {
                Text(metric)
                    .font(.system(size: 44, weight: .black))
                    .kerning(-0.5)
                    .foregroundStyle(metricColor)
                    .lineLimit(1)
                    .minimumScaleFactor(0.6)
                if let metricSuffix {
                    Text(metricSuffix)
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(.white.opacity(0.6))
                }
            }
            if let sub, !sub.isEmpty {
                Text(sub)
                    .font(.system(size: 14))
                    .foregroundStyle(.white.opacity(0.9))
            }
            extra
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.Token.horizon500.color)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

// md-page-actions: Back pill (sub-pages) + "Edit details" pill that opens Fyn
// pre-asked with the page's holdings.
struct MobilePageActions: View {
    var onBack: (() -> Void)?
    var editDetails: (() -> Void)?

    var body: some View {
        HStack(spacing: 8) {
            if let onBack {
                Button(action: onBack) {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                            .font(.system(size: 12, weight: .bold))
                        Text("Back")
                    }
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .padding(.vertical, 7)
                    .padding(.leading, 10)
                    .padding(.trailing, 14)
                    .background(Color.white)
                    .clipShape(Capsule())
                    .overlay(
                        Capsule().stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
                    )
                }
                .accessibilityIdentifier("chrome.back")
            }

            if let editDetails {
                Button(action: editDetails) {
                    Text("Edit details")
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.raspberry600.color)
                        .padding(.vertical, 7)
                        .padding(.horizontal, 14)
                        .background(Color.white)
                        .clipShape(Capsule())
                        .overlay(
                            Capsule().stroke(FynlaColor.Token.raspberry500.color.opacity(0.55), lineWidth: 1)
                        )
                }
                .accessibilityIdentifier("chrome.edit-details")
            }

            Spacer(minLength: 0)
        }
        .padding(.horizontal, 16)
        .padding(.top, 14)
        .padding(.bottom, 4)
    }
}
