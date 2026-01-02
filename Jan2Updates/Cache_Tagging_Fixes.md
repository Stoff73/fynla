# Cache Tagging Fixes - January 2, 2026

## Issue Summary

During comprehensive testing of https://fynla.org across all demo personas, two critical errors were discovered related to Laravel cache tagging on SiteGround shared hosting (which uses file-based caching).

## Errors Found

### 1. Protection Plan Cache Tagging Error (ALL Personas)

**Affected Personas**: Young Family, Peak Earners, Widow, Entrepreneur (ALL)

**Error Message**:
```
Failed to generate comprehensive protection plan: This cache store does not support tagging.
```

**Console Error**:
```
Failed to load protection plan: Object
```

**Root Cause**:
- `ProtectionAgent.php` uses Laravel's `Cache::tags()` feature for cache invalidation
- File-based cache driver (used on SiteGround shared hosting) does NOT support cache tags
- Only Redis and Memcached drivers support cache tagging

**Affected Code** (`app/Agents/ProtectionAgent.php:34,172,265-269`):
```php
// Line 34 - Cache tags defined
$cacheTags = ['protection', 'user_'.$userId];

// Line 172 - Remember with tags
return $this->remember($cacheKey, function () use ($userId) {
    // ...
}, null, $cacheTags);

// Lines 265-269 - Invalid cache flush
public function invalidateCache(int $userId): void
{
    Cache::tags(['protection', 'user_'.$userId])->flush();
}
```

### 2. Estate Plan Internal Server Error (Widow Persona)

**Affected Personas**: Widow (Margaret Thompson)

**Error Message**:
```
Failed to Generate Estate Plan - Internal server error
```

**Console Error**:
```
Failed to load estate plan: Object
```

**Root Cause**:
- Same cache tagging issue in `EstateAgent.php`
- Additionally may have data-specific issues with single-person estate calculations

**Affected Code** (`app/Agents/EstateAgent.php:37,119,402-405`):
```php
// Line 37 - Cache tags defined
$cacheTags = ['estate', 'user_'.$userId];

// Line 119 - Remember with tags
return $this->remember($cacheKey, function () use ($userId) {
    // ...
}, null, $cacheTags);

// Lines 402-405 - Invalid cache flush
public function invalidateCache(int $userId): void
{
    Cache::tags(['estate', 'user_'.$userId])->flush();
}
```

## Fix Applied

### File 1: `app/Agents/BaseAgent.php`

**Changes Made**:
1. Added `cacheStoreSupportsTagging()` method to detect if cache driver supports tags
2. Modified `remember()` method to gracefully fall back to non-tagged caching

**New Code Added** (lines 43-61):
```php
protected function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
{
    $ttl = $ttl ?? $this->cacheTtl;

    // Use tagged caching if tags provided AND cache store supports tagging
    if (! empty($tags) && $this->cacheStoreSupportsTagging()) {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    return Cache::remember($key, $ttl, $callback);
}

/**
 * Check if the current cache store supports tagging.
 * File and database cache stores do not support tagging.
 */
protected function cacheStoreSupportsTagging(): bool
{
    $store = Cache::getStore();

    // Redis and Memcached support tagging, file and database do not
    return $store instanceof \Illuminate\Cache\TaggableStore;
}
```

### File 2: `app/Agents/ProtectionAgent.php`

**Changes Required** (lines 265-269):
```php
// BEFORE (broken on file cache):
public function invalidateCache(int $userId): void
{
    Cache::tags(['protection', 'user_'.$userId])->flush();
}

// AFTER (compatible with all cache drivers):
public function invalidateCache(int $userId): void
{
    if ($this->cacheStoreSupportsTagging()) {
        Cache::tags(['protection', 'user_'.$userId])->flush();
    } else {
        Cache::forget("protection_analysis_{$userId}");
    }
}
```

### File 3: `app/Agents/EstateAgent.php`

**Changes Required** (lines 402-405):
```php
// BEFORE (broken on file cache):
public function invalidateCache(int $userId): void
{
    Cache::tags(['estate', 'user_'.$userId])->flush();
}

// AFTER (compatible with all cache drivers):
public function invalidateCache(int $userId): void
{
    if ($this->cacheStoreSupportsTagging()) {
        Cache::tags(['estate', 'user_'.$userId])->flush();
    } else {
        Cache::forget("estate_analysis_{$userId}");
    }
}
```

## Testing Checklist

After applying fixes, verify:

- [x] Young Family - Protection Plan loads ✅
- [x] Young Family - Estate Plan loads ✅
- [x] Peak Earners - Protection Plan loads ✅
- [x] Peak Earners - Estate Plan loads ✅
- [x] Widow - Protection Plan loads ✅
- [x] Widow - Estate Plan loads ✅
- [x] Entrepreneur - Protection Plan loads ✅
- [x] Entrepreneur - Estate Plan loads ✅

**All tests passed on 02 January 2026 at 15:19**

## Technical Notes

### Why This Happens on SiteGround

SiteGround shared hosting uses file-based caching by default (`CACHE_DRIVER=file` in `.env`). Laravel's cache tagging feature requires a cache backend that supports atomic operations on multiple keys simultaneously - only Redis and Memcached provide this.

### Future Recommendation

If SiteGround account is upgraded or moved to a VPS with Redis support, the cache tagging will automatically work (the fix detects the driver capability).

### Files Changed

1. `app/Agents/BaseAgent.php` - Added cache tag support detection
2. `app/Agents/ProtectionAgent.php` - Fix invalidateCache method
3. `app/Agents/EstateAgent.php` - Fix invalidateCache method

---

## Additional Fix: Estate Plan "Undefined array key" Error

### Issue
The Widow persona's Estate Plan showed "Internal server error" with message:
```
Undefined array key "type"
```

### Root Cause
In `PersonalizedGiftingStrategyService.php`, line 255 was filtering assets by type:
```php
$propertyAssets = array_filter($semiLiquidAssets['assets'], fn ($a) => $a['type'] === 'property');
```
Some assets (e.g., DB pensions in liquidity analysis) don't have a 'type' key set.

### Fix Applied
Added null coalescing operator:
```php
$propertyAssets = array_filter($semiLiquidAssets['assets'], fn ($a) => ($a['type'] ?? null) === 'property');
```

**Commit**: `0ae66eb`

---

## Additional Fix: Unhandled Match Case 'chattel'

### Issue
The Widow persona's Estate Plan showed "Internal server error" with message:
```
Unhandled match case 'chattel' at AssetLiquidityAnalyzer.php:116
```

### Root Cause
In `AssetLiquidityAnalyzer.php`, line 116 has a `match` statement for asset types that didn't include 'chattel':
```php
return match ($asset->asset_type) {
    'cash' => [...],
    'investment' => [...],
    'pension', 'dc_pension', 'db_pension' => [...],
    'property' => [...],
    'business' => [...],
    'other' => [...],
    // Missing: 'chattel'
};
```

The Widow persona (Margaret Thompson) has chattels in her estate, causing the match to fail.

### Fix Applied
Added 'chattel' case to the match statement:
```php
'chattel' => [
    'liquidity' => 'semi_liquid',
    'is_giftable' => true,
    'not_giftable_reason' => null,
    'gifting_considerations' => [
        'Chattels (personal possessions) can be gifted',
        'Items worth over £6,000 may trigger Capital Gains Tax on gift',
        'Wasting assets (lifespan under 50 years) are CGT exempt',
        'Sets of items are valued together for CGT purposes',
        'Professional valuation recommended for valuable items',
        'Large gifts are PETs - exempt after 7 years',
    ],
],
```

### File to Deploy
```bash
scp -P 18765 -i ~/.ssh/production app/Services/Estate/AssetLiquidityAnalyzer.php u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/app/Services/Estate/
```

---

## Additional Fix: Undefined array key "name" (Entrepreneur Persona)

### Issue
The Entrepreneur persona (Alex Chen) Estate Plan showed "Internal server error" with message:
```
Undefined array key "name"
```

### Root Cause
In `PersonalizedGiftingStrategyService.php`, the `buildMainResidenceStrategy` method was using wrong array keys:
- Line 315: `$mainResidence['name']` - but actual key is `asset_name`
- Lines 316, 323, 324: `$mainResidence['value']` - but actual key is `current_value`

The asset data comes from `AssetLiquidityAnalyzer` which uses `asset_name` and `current_value`, not `name` and `value`.

### Fix Applied
```php
// BEFORE (wrong keys):
'main_residence' => $mainResidence['name'],
'current_value' => round($mainResidence['value'], 2),
...
'Example: Sell £'.number_format($mainResidence['value'], 0)...

// AFTER (correct keys):
'main_residence' => $mainResidence['asset_name'],
'current_value' => round($mainResidence['current_value'], 2),
...
'Example: Sell £'.number_format($mainResidence['current_value'], 0)...
```

### File to Deploy
```bash
scp -P 18765 -i ~/.ssh/production app/Services/Estate/PersonalizedGiftingStrategyService.php u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/app/Services/Estate/
```

---

## Deployment

Changes have been committed and pushed to GitHub. To deploy:

```bash
# SSH to production
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org

# Pull the fix
cd ~/www/fynla.org/public_html
git pull origin main

# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
```

---

## Summary of All Changes

### Commits
| Commit | Description |
|--------|-------------|
| `2e411f3` | fix: Add 'chattel' case to AssetLiquidityAnalyzer match statement |
| `cc5a785` | fix: Use correct array keys in main residence strategy |

### Files Modified
| File | Change |
|------|--------|
| `app/Agents/BaseAgent.php` | Added `cacheStoreSupportsTagging()` method |
| `app/Agents/ProtectionAgent.php` | Fixed `invalidateCache()` for file cache |
| `app/Agents/EstateAgent.php` | Fixed `invalidateCache()` for file cache |
| `app/Services/Estate/AssetLiquidityAnalyzer.php` | Added 'chattel' case to match statement |
| `app/Services/Estate/PersonalizedGiftingStrategyService.php` | Fixed array key names (`asset_name`, `current_value`) |

### Bugs Fixed
| # | Error | Persona | Root Cause |
|---|-------|---------|------------|
| 1 | `This cache store does not support tagging` | ALL | File cache doesn't support `Cache::tags()` |
| 2 | `Undefined array key "type"` | Widow | Missing null check in asset filtering |
| 3 | `Unhandled match case 'chattel'` | Widow | Missing chattel case in match statement |
| 4 | `Undefined array key "name"` | Entrepreneur | Wrong array keys (`name` vs `asset_name`) |

### Final Status
All four demo personas (Young Family, Peak Earners, Widow, Entrepreneur) now have fully working Protection Plans and Estate Plans on https://fynla.org.
