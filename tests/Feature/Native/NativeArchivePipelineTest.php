<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('provides native-only production build archive and verification scripts', function (): void {
    $directory = base_path('deploy/mobile-native');
    $scripts = [
        'build.sh' => ['Fynla-Production', 'Release', 'generic/platform=iOS'],
        'archive.sh' => [
            'Fynla-Production',
            'Release',
            'generic/platform=iOS',
            'MARKETING_VERSION',
            'CURRENT_PROJECT_VERSION',
        ],
        'verify-archive.sh' => [
            'org.fynla.app',
            'PrivacyInfo.xcprivacy',
            'NSFaceIDUsageDescription',
            'com\\.apple\\.developer\\.associated-domains',
            'aps-environment',
            'Capacitor',
            'cordova',
            'WKWebView',
        ],
    ];

    foreach ($scripts as $filename => $needles) {
        $path = "{$directory}/{$filename}";
        expect($path)->toBeFile()->and(is_executable($path))->toBeTrue();

        $contents = File::get($path);
        expect($contents)->toContain(...$needles)
            ->not->toContain('npm ', 'npx ', 'vite ', 'cap sync');
    }

    expect("{$directory}/README.md")->toBeFile()
        ->and(base_path('docs/app-store/native-v1-release-checklist.md'))->toBeFile();
});

it('accepts only a signed native production archive without forbidden payloads', function (): void {
    if (! is_executable('/usr/bin/codesign')) {
        $this->markTestSkipped('Archive signing verification requires macOS codesign.');
    }

    $fixture = nativeArchiveFixture();

    try {
        $valid = nativeArchiveVerify($fixture['archive']);
        expect($valid->isSuccessful())->toBeTrue($valid->getErrorOutput())
            ->and($valid->getOutput())->toContain(
                'Bundle ID: org.fynla.app',
                'Minimum iOS: 17.0',
                'Archive verification passed.',
            );

        File::put("{$fixture['app']}/unexpected.html", '<html></html>');
        nativeArchiveSign($fixture['app'], $fixture['entitlements']);

        $webPayload = nativeArchiveVerify($fixture['archive']);
        expect($webPayload->isSuccessful())->toBeFalse()
            ->and($webPayload->getErrorOutput())->toContain('web bundle');

        File::delete("{$fixture['app']}/unexpected.html");
        File::put("{$fixture['app']}/environment.txt", 'https://csjones.co/fynla');
        nativeArchiveSign($fixture['app'], $fixture['entitlements']);

        $staging = nativeArchiveVerify($fixture['archive']);
        expect($staging->isSuccessful())->toBeFalse()
            ->and($staging->getErrorOutput())->toContain('staging host');
    } finally {
        File::deleteDirectory($fixture['root']);
    }
});

/** @return array{root: string, archive: string, app: string, entitlements: string} */
function nativeArchiveFixture(): array
{
    $root = sys_get_temp_dir().'/fynla-native-archive-'.Str::uuid();
    $archive = "{$root}/Fynla.xcarchive";
    $app = "{$archive}/Products/Applications/Fynla.app";
    $entitlements = "{$root}/entitlements.plist";

    File::ensureDirectoryExists($app);
    File::copy('/usr/bin/true', "{$app}/Fynla");
    chmod("{$app}/Fynla", 0755);
    File::copy(
        base_path('ios-native/Fynla/Resources/PrivacyInfo.xcprivacy'),
        "{$app}/PrivacyInfo.xcprivacy",
    );
    File::put("{$app}/Info.plist", <<<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>CFBundleIdentifier</key><string>org.fynla.app</string>
<key>CFBundleExecutable</key><string>Fynla</string>
<key>CFBundlePackageType</key><string>APPL</string>
<key>CFBundleShortVersionString</key><string>1.0</string>
<key>CFBundleVersion</key><string>42</string>
<key>MinimumOSVersion</key><string>17.0</string>
<key>UIDeviceFamily</key><array><integer>1</integer></array>
<key>NSFaceIDUsageDescription</key><string>Use Face ID to unlock your financial plan.</string>
</dict></plist>
PLIST);
    File::put($entitlements, <<<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>application-identifier</key><string>99S3M8JLLF.org.fynla.app</string>
<key>aps-environment</key><string>production</string>
<key>com.apple.developer.associated-domains</key><array><string>applinks:fynla.org</string></array>
</dict></plist>
PLIST);

    nativeArchiveSign($app, $entitlements);

    return compact('root', 'archive', 'app', 'entitlements');
}

function nativeArchiveSign(string $app, string $entitlements): void
{
    $process = new Process([
        '/usr/bin/codesign',
        '--force',
        '--sign',
        '-',
        '--entitlements',
        $entitlements,
        $app,
    ]);
    $process->mustRun();
}

function nativeArchiveVerify(string $archive): Process
{
    $process = new Process([
        '/bin/bash',
        base_path('deploy/mobile-native/verify-archive.sh'),
        $archive,
    ]);
    $process->run();

    return $process;
}
