# Fyn AI Connection Drop — Root Cause & Fix

**Date:** 2 April 2026
**Reported by:** Brett Isenberg (user 491, brett.isenberg@capitoul.co.uk)
**Symptom:** Fyn stops responding mid-conversation — user sends message, no reply appears, Fyn goes dead

---

## Root Cause: PHP `default_socket_timeout: 20` on production

The production server (SiteGround) has PHP's `default_socket_timeout` set to **20 seconds**. The OpenAI PHP SDK (`XaiClient.php`) had **no custom HTTP client configured**, so it fell back to this 20-second socket timeout.

The chat model `grok-4-1-fast-reasoning` is a **reasoning model** — it "thinks" before responding. During the thinking phase, **no streaming chunks are sent** to the server. As the conversation grows (more tokens = more thinking time), the reasoning phase eventually exceeds 20 seconds, and PHP kills the socket silently.

### How it manifested in Brett's conversation

| Message | Input Tokens | Result |
|---------|-------------|--------|
| "What is my total net worth?" | 16.4k | Responded |
| "How much am I worth in total?" | 17.0k | Responded |
| "How many properties do I own?" | 17.2k | Responded |
| "What is my total net worth?" (repeat) | 18.0k | Responded |
| "How much am I worth in total?" (repeat) | 36.7k | Responded (tool calls) |
| "What investments do I have?" | 37.6k | Responded (tool calls) |
| "What is inheritance tax?" | 45.8k | Responded (tool calls, 328 output tokens) |
| **"How much cash do I have across all my accounts?"** | **~50k+** | **NO RESPONSE** |
| **"How much cash do I have in my bank accounts?"** | **~50k+** | **NO RESPONSE** |

Token context grew with each message. By messages 8-9, reasoning time exceeded the 20-second socket timeout.

### Why no error was shown

1. **Backend:** The socket timeout closes the stream silently — no PHP exception is thrown, the `foreach ($stream)` loop just exits. `$fullResponse` stays empty. A `done` SSE event is emitted with empty content.
2. **Frontend:** The `done` handler checks `if (state.streamingText)` — it's empty, so no assistant message is added. No error is set. Fyn just goes silent.

---

## Fixes Applied

### 1. XaiClient.php — HTTP timeout set to 120 seconds (CRITICAL)

```php
$httpClient = new GuzzleClient([
    'timeout' => 120,        // 2 minutes for reasoning models
    'connect_timeout' => 10, // 10 seconds to establish connection
]);

$this->client = OpenAI::factory()
    ->withApiKey($apiKey)
    ->withBaseUri($baseUrl)
    ->withHttpClient($httpClient)
    ->make();
```

This overrides the production `default_socket_timeout: 20` with an explicit 120-second Guzzle timeout, giving reasoning models enough time to think with large contexts.

### 2. aiChat.js — Empty response detection

Added check in the `finally` block of `sendMessage()`:

```javascript
if (state.streaming && !state.streamingText && !state.error) {
    commit('SET_ERROR', "Fyn couldn't generate a response. This can happen with longer conversations — try starting a new one.");
}
```

If the stream completes but Fyn never produced any text, the user now sees a helpful error instead of silence.

### 3. IHTCalculationService.php — Carbon to string type fix

Fixed all 4 call sites to `projectSingleLiability()` to safely convert Carbon/DateTime objects to `Y-m-d` strings:

- User mortgages (`$mortgage->end_date`)
- User liabilities (`$liability->maturity_date ?? estimatePayoffDate()`)
- Spouse mortgages (`$mortgage->end_date`)
- Spouse liabilities (`$liability->maturity_date ?? estimatePayoffDate()`)

This was causing a `TypeError` crash for Brett (user 491) — `Argument #2 ($endDate) must be of type ?string, Illuminate\Support\Carbon given`. The error showed 10+ times in the production logs at 09:11-09:39 on 2 April.

---

## Files Changed

| File | Change |
|------|--------|
| `app/Services/AI/XaiClient.php` | Added GuzzleHttp\Client with 120s timeout |
| `app/Services/Estate/IHTCalculationService.php` | Fixed 4 Carbon→string conversions in projectLiabilities() |
| `resources/js/store/modules/aiChat.js` | Added empty response detection in sendMessage() finally block |

## Deploy

1. Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`
2. Upload both PHP files
3. Clear caches: `php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`

## Verification

After deploy, test by having a 10+ message conversation with Fyn on production. The connection should no longer drop. If it does, check `storage/logs/laravel.log` for `[CoordinatingAgent] xAI API streaming failed` entries — the 120s timeout should now surface proper exceptions rather than silent drops.
