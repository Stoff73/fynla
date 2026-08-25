#!/usr/bin/env bash
#
# Shared JSON helpers for the Stop hooks.
#
# The hooks used to reach for `jq` for both reading the harness payload and
# emitting their decision. `jq` is not a dependency of this repository and is
# absent on at least one active development machine, where every emit was
# discarded and the hooks looked like a clean pass. PHP is a hard dependency, so
# use that. See W-0483.

# json_field <json> <key> <default>
json_field() {
  local json="$1" key="$2" default="$3" out
  out=$(printf '%s' "$json" | php -r '
$raw = stream_get_contents(STDIN);
$key = $argv[1];
$default = $argv[2];
$data = json_decode($raw, true);
if (! is_array($data) || ! array_key_exists($key, $data) || $data[$key] === null) {
    echo $default;
    exit(0);
}
$v = $data[$key];
echo is_bool($v) ? ($v ? "true" : "false") : (string) $v;
' "$key" "$default" 2>/dev/null) || out=""
  printf '%s' "${out:-$default}"
}

# json_emit <key> <string-value>   — prints {"<key>": "<escaped value>"}
json_emit() {
  printf '%s' "$2" | php -r '
$value = stream_get_contents(STDIN);
echo json_encode([$argv[1] => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
' "$1" 2>/dev/null
}

# json_emit_block <reason>   — prints {"decision":"block","reason":"..."}
json_emit_block() {
  printf '%s' "$1" | php -r '
$reason = stream_get_contents(STDIN);
echo json_encode(["decision" => "block", "reason" => $reason], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
' 2>/dev/null
}
