import Foundation
import Testing
@testable import Fynla

@Suite("Native design system")
struct DesignSystemTests {
    @Test
    func exposesOnlyTheApprovedNamedLightPalette() {
        #expect(FynlaColor.Token.allCases.map(\.assetName) == [
            "Eggshell50",
            "Eggshell500",
            "Horizon200",
            "Horizon500",
            "Neutral500",
            "Raspberry100",
            "Raspberry500",
            "Raspberry600",
            "Raspberry700",
            "Raspberry800",
            "Savannah100",
            "Violet500",
        ])
        #expect(FynlaColor.primaryActionToken == .raspberry500)
        #expect(FynlaColor.primaryTextToken == .horizon500)
        #expect(FynlaColor.pageBackgroundToken == .eggshell500)
        #expect(FynlaColor.focusToken == .violet500)
    }

    @Test
    func spacingUsesTheApprovedFourPointScaleAndAccessibleTarget() {
        #expect(FynlaSpacing.micro == 2)
        #expect(FynlaSpacing.xSmall == 4)
        #expect(FynlaSpacing.small == 8)
        #expect(FynlaSpacing.medium == 12)
        #expect(FynlaSpacing.standard == 16)
        #expect(FynlaSpacing.large == 24)
        #expect(FynlaSpacing.xLarge == 32)
        #expect(FynlaSpacing.minimumInteractiveTarget == 44)
    }

    @Test
    func buttonVariantsUseApprovedTextOnlySemanticTokens() {
        #expect(FynlaButton.Variant.primary.backgroundToken == .raspberry600)
        #expect(FynlaButton.Variant.primary.pressedBackgroundToken == .raspberry700)
        #expect(FynlaButton.Variant.primary.foregroundToken == .eggshell50)
        #expect(FynlaButton.Variant.primary.borderToken == nil)

        #expect(FynlaButton.Variant.secondary.backgroundToken == .eggshell50)
        #expect(FynlaButton.Variant.secondary.pressedBackgroundToken == .savannah100)
        #expect(FynlaButton.Variant.secondary.foregroundToken == .horizon500)
        #expect(FynlaButton.Variant.secondary.borderToken == .horizon200)

        #expect(FynlaButton.Variant.destructive.backgroundToken == .raspberry600)
        #expect(FynlaButton.Variant.destructive.pressedBackgroundToken == .raspberry800)
        #expect(FynlaButton.Variant.destructive.foregroundToken == .eggshell50)
        #expect(FynlaButton.Variant.destructive.borderToken == nil)
    }

    @Test
    func buttonMinimumSizeCoversBothInteractiveDimensions() {
        #expect(FynlaButton.minimumSize.width == 44)
        #expect(FynlaButton.minimumSize.height == 44)
    }

    @Test
    func colorAssetSourcesContainOneUniversalLightSRGBValue() throws {
        let sourceFile = URL(fileURLWithPath: #filePath).resolvingSymlinksInPath()
        let assets = sourceFile
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .appending(path: "Fynla/Assets.xcassets")

        for token in FynlaColor.Token.allCases {
            let contents = assets
                .appending(path: "\(token.assetName).colorset/Contents.json")
            let object = try JSONSerialization.jsonObject(with: Data(contentsOf: contents))
            let root = try #require(object as? [String: Any])
            let colors = try #require(root["colors"] as? [[String: Any]])
            #expect(colors.count == 1)

            let entry = try #require(colors.first)
            #expect(entry["idiom"] as? String == "universal")
            #expect(entry["appearances"] == nil)

            let color = try #require(entry["color"] as? [String: Any])
            #expect(color["color-space"] as? String == "srgb")
            let components = try #require(color["components"] as? [String: String])
            #expect(Set(components.keys) == ["red", "green", "blue", "alpha"])
        }
    }

    @Test
    func loadingIndicatorStopsAnimatingForReduceMotion() {
        #expect(LoadingView.showsAnimatedIndicator(reduceMotion: false))
        #expect(!LoadingView.showsAnimatedIndicator(reduceMotion: true))
    }
}

#if canImport(UIKit)
import UIKit

extension DesignSystemTests {
    @Test
    func everyPaletteTokenResolvesToTheApprovedLightOnlyValue() throws {
        let expected: [FynlaColor.Token: (CGFloat, CGFloat, CGFloat, CGFloat)] = [
            .eggshell50: (1, 1, 1, 1),
            .eggshell500: (0.968627, 0.964706, 0.956863, 1),
            .horizon200: (0.886275, 0.909804, 0.941176, 1),
            .horizon500: (0.121569, 0.164706, 0.266667, 1),
            .neutral500: (0.443137, 0.443137, 0.443137, 1),
            .raspberry100: (0.988235, 0.905882, 0.952941, 1),
            .raspberry500: (0.909804, 0.243137, 0.427451, 1),
            .raspberry600: (0.858824, 0.152941, 0.466667, 1),
            .raspberry700: (0.745098, 0.094118, 0.364706, 1),
            .raspberry800: (0.615686, 0.090196, 0.301961, 1),
            .savannah100: (0.992157, 0.980392, 0.968627, 1),
            .violet500: (0.345098, 0.329412, 0.901961, 1),
        ]

        for token in FynlaColor.Token.allCases {
            let approved = try #require(expected[token])
            for style in [UIUserInterfaceStyle.light, .dark] {
                let traits = UITraitCollection(userInterfaceStyle: style)
                let color = try #require(
                    UIColor(named: token.assetName, in: .main, compatibleWith: traits),
                    "Missing named colour asset \(token.assetName)"
                )
                var red: CGFloat = 0
                var green: CGFloat = 0
                var blue: CGFloat = 0
                var alpha: CGFloat = 0
                #expect(color.getRed(&red, green: &green, blue: &blue, alpha: &alpha))
                #expect(abs(red - approved.0) < 0.000_01)
                #expect(abs(green - approved.1) < 0.000_01)
                #expect(abs(blue - approved.2) < 0.000_01)
                #expect(abs(alpha - approved.3) < 0.000_01)
            }
        }
    }
}
#endif
