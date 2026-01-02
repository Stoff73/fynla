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

- [ ] Young Family - Protection Plan loads
- [ ] Young Family - Estate Plan loads
- [ ] Peak Earners - Protection Plan loads
- [ ] Peak Earners - Estate Plan loads
- [ ] Widow - Protection Plan loads
- [ ] Widow - Estate Plan loads
- [ ] Entrepreneur - Protection Plan loads
- [ ] Entrepreneur - Estate Plan loads

## Technical Notes

### Why This Happens on SiteGround

SiteGround shared hosting uses file-based caching by default (`CACHE_DRIVER=file` in `.env`). Laravel's cache tagging feature requires a cache backend that supports atomic operations on multiple keys simultaneously - only Redis and Memcached provide this.

### Future Recommendation

If SiteGround account is upgraded or moved to a VPS with Redis support, the cache tagging will automatically work (the fix detects the driver capability).

### Files Changed

1. `app/Agents/BaseAgent.php` - Added cache tag support detection
2. `app/Agents/ProtectionAgent.php` - Fix invalidateCache method
3. `app/Agents/EstateAgent.php` - Fix invalidateCache method

## Deployment

Changes have been committed and pushed to GitHub (commit 2459380). To deploy:

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
