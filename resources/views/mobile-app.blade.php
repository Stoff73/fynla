<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F7F6F4">
    <title>Fynla</title>
    @php
        $buildDirectory = app()->environment('e2e') ? 'm-e2e-build' : 'm-build';
        $manifestPath = public_path($buildDirectory . '/manifest.json');
        $entryJs = null;
        $entryCss = [];
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $entry = $manifest['resources/mobile/main.js'] ?? null;
            if ($entry) {
                $entryJs = asset($buildDirectory . '/' . $entry['file']);
                foreach (($entry['css'] ?? []) as $css) {
                    $entryCss[] = asset($buildDirectory . '/' . $css);
                }
            }
        }
    @endphp
    @foreach ($entryCss as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
</head>
<body>
    <div id="m-app"></div>
    @if ($entryJs)
        <script type="module" src="{{ $entryJs }}"></script>
    @else
        <p style="font-family:sans-serif;padding:24px">Mobile build missing. Run <code>npm run build:mobile</code>.</p>
    @endif
</body>
</html>
