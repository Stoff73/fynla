<?php

declare(strict_types=1);

it('declares the exact native privacy collection and required reason api inventory', function (): void {
    $path = base_path('ios-native/Fynla/Resources/PrivacyInfo.xcprivacy');

    expect($path)->toBeFile();

    $xml = simplexml_load_file($path);
    expect($xml)->toBeInstanceOf(SimpleXMLElement::class);

    /** @var array<string, mixed> $manifest */
    $manifest = nativePrivacyPlistValue($xml->dict[0]);

    expect($manifest['NSPrivacyTracking'])->toBeFalse()
        ->and($manifest['NSPrivacyTrackingDomains'])->toBe([])
        ->and(nativePrivacyAccessedAPIs($manifest))->toBe([
            'NSPrivacyAccessedAPICategoryFileTimestamp' => ['C617.1'],
            'NSPrivacyAccessedAPICategoryUserDefaults' => ['CA92.1'],
        ])
        ->and(nativePrivacyCollectedData($manifest))->toBe([
            'NSPrivacyCollectedDataTypeName' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeEmailAddress' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypePhoneNumber' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypePhysicalAddress' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeHealth' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
                'NSPrivacyCollectedDataTypePurposeProductPersonalization',
            ],
            'NSPrivacyCollectedDataTypeOtherFinancialInfo' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
                'NSPrivacyCollectedDataTypePurposeProductPersonalization',
            ],
            'NSPrivacyCollectedDataTypeOtherUserContent' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
                'NSPrivacyCollectedDataTypePurposeProductPersonalization',
            ],
            'NSPrivacyCollectedDataTypeCustomerSupport' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeUserID' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeDeviceID' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypePurchaseHistory' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeOtherUsageData' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeOtherDiagnosticData' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
            ],
            'NSPrivacyCollectedDataTypeOtherDataTypes' => [
                'NSPrivacyCollectedDataTypePurposeAppFunctionality',
                'NSPrivacyCollectedDataTypePurposeProductPersonalization',
            ],
        ]);
});

/** @return mixed */
function nativePrivacyPlistValue(SimpleXMLElement $node)
{
    return match ($node->getName()) {
        'dict' => nativePrivacyPlistDictionary($node),
        'array' => array_map(
            nativePrivacyPlistValue(...),
            nativePrivacyPlistChildren($node),
        ),
        'true' => true,
        'false' => false,
        'integer' => (int) $node,
        default => (string) $node,
    };
}

/** @return array<string, mixed> */
function nativePrivacyPlistDictionary(SimpleXMLElement $node): array
{
    $children = nativePrivacyPlistChildren($node);
    $dictionary = [];

    for ($index = 0; $index < count($children); $index += 2) {
        $key = (string) $children[$index];
        $dictionary[$key] = nativePrivacyPlistValue($children[$index + 1]);
    }

    return $dictionary;
}

/** @return list<SimpleXMLElement> */
function nativePrivacyPlistChildren(SimpleXMLElement $node): array
{
    $children = [];
    foreach ($node->children() as $child) {
        $children[] = $child;
    }

    return $children;
}

/** @param array<string, mixed> $manifest
 * @return array<string, list<string>>
 */
function nativePrivacyAccessedAPIs(array $manifest): array
{
    $apis = [];
    foreach ($manifest['NSPrivacyAccessedAPITypes'] as $entry) {
        $apis[$entry['NSPrivacyAccessedAPIType']] = $entry['NSPrivacyAccessedAPITypeReasons'];
    }
    ksort($apis);

    return $apis;
}

/** @param array<string, mixed> $manifest
 * @return array<string, list<string>>
 */
function nativePrivacyCollectedData(array $manifest): array
{
    $collected = [];
    foreach ($manifest['NSPrivacyCollectedDataTypes'] as $entry) {
        expect($entry['NSPrivacyCollectedDataTypeLinked'])->toBeTrue()
            ->and($entry['NSPrivacyCollectedDataTypeTracking'])->toBeFalse();
        $collected[$entry['NSPrivacyCollectedDataType']] =
            $entry['NSPrivacyCollectedDataTypePurposes'];
    }

    return $collected;
}
