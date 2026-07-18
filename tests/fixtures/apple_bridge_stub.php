<?php

declare(strict_types=1);

$raw = stream_get_contents(STDIN);
$request = json_decode($raw, true);
$scenario = is_array($request) ? ($request['scenario'] ?? 'success') : 'malformed_input';

$write = static function (array $envelope, bool $newline = true): void {
    echo json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if ($newline) {
        echo "\n";
    }
};

if ($scenario === 'timeout') {
    usleep(500_000);
}

if ($scenario === 'malformed') {
    echo '{not-json';
    exit(0);
}

if ($scenario === 'oversized') {
    echo str_repeat('x', 16_384);
    exit(0);
}

if ($scenario === 'multiple') {
    $write(['version' => 1, 'ok' => true, 'data' => []]);
    $write(['version' => 1, 'ok' => true, 'data' => []]);
    exit(0);
}

if ($scenario === 'stderr') {
    fwrite(STDERR, "sensitive source detail\n");
}

if ($scenario === 'failure' || $scenario === 'failure_zero') {
    $write([
        'version' => 1,
        'ok' => false,
        'error' => ['code' => 'invalid_signed_data', 'retryable' => false],
    ]);
    exit($scenario === 'failure' ? 2 : 0);
}

if ($scenario === 'bad_failure_shape') {
    $write([
        'version' => 1,
        'ok' => false,
        'error' => ['code' => 'invalid_signed_data', 'retryable' => 'false'],
    ]);
    exit(2);
}

$version = $scenario === 'wrong_version' ? 2 : 1;
$envelope = [
    'version' => $version,
    'ok' => true,
    'data' => [
        'argv' => $_SERVER['argv'],
        'environment_keys' => array_keys(getenv()),
        'stdin_sha256' => hash('sha256', $raw),
        'contains_signed_value' => str_contains($raw, 'private.signed.value'),
    ],
];

if ($scenario === 'list_data') {
    $envelope['data'] = ['unexpected'];
}

if ($scenario === 'extra_envelope_field') {
    $envelope['debug'] = 'unsafe';
}

$write($envelope, $scenario !== 'no_newline');
exit($scenario === 'success_nonzero' ? 2 : 0);
