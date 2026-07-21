# npm audit — deferred advisories

Last reviewed: 2026-05-12 (audit-npm-deps branch / W1-N).

Tracks open `npm audit` advisories that have **not** been resolved on the current
branch and the rationale for deferral.

After running `npm audit fix` (non-breaking) on this branch and pinning one
build-time dependency for Node-18 compatibility, 5 advisories remain (npm
counts these as 8 entries because of transitive chains; the root is 5).
Each is justified individually below.

---

## 1. `@capgo/capacitor-native-biometric` < 8.3.6 — moderate (Android-only)

- **Advisory:** [GHSA-vx5f-vmr6-32wf](https://github.com/advisories/GHSA-vx5f-vmr6-32wf)
- **Current installed:** 6.0.4 (constrained by `package.json` to `^6.0.4`)
- **Patched version:** 8.3.6+
- **Status:** Deferred.

### Why deferred

The vulnerability is **Android-specific**. The flawed code is in
`AuthActivity.java` in the plugin's Android implementation; the
`onAuthenticationSucceeded()` callback does not handle a `CryptoObject` and only
checks that the callback fires, allowing an attacker who can hook the callback
to bypass biometric verification.

Fynla **does not ship an Android build**. The mobile app is iOS-only — see
`CLAUDE.md` "Mobile App (Capacitor iOS)", `deploy/mobile/build-ios.sh`, and the
absence of any `android/` directory. The vulnerable native code path is never
present in any deployed artefact.

### Why the upgrade is non-trivial

`@capgo/capacitor-native-biometric@8.3.6+` requires `@capacitor/core@>=8.0.0`
as a peer dependency. Fynla currently runs the Capacitor 6 stack across every
`@capacitor/*` package (`@capacitor/core@^6.2.1`, `@capacitor/ios@^6.2.1`,
plus 13 other Capacitor packages all on `^6.x`). Bumping the biometric plugin
in isolation would break the peer-dependency tree.

A full Capacitor 6 → 8 stack upgrade is a multi-day mobile-engineering task
that needs:

- Coordinated bumps across every `@capacitor/*` package
- Xcode `pod install` + native-code review (the Capacitor 8 migration guide
  rebases the iOS bridge)
- Re-verification of every iOS gotcha catalogued in `CLAUDE.md` (PWA
  conditional, `transformAssetUrls: false`, image-MIME rules in
  `vite.config.js`, Face ID Keychain integration)
- Face ID setup + login smoke on a physical device
- Voice input regression (the `@capacitor-community/speech-recognition` v6
  peer is also affected)

This is tracked as a separate engineering ticket and **must** be planned
end-to-end before execution — it is not appropriate as a quick-win.

### If Fynla ever adds Android

This deferral immediately becomes blocking. Re-evaluate before adding any
Android target.

---

## 2. `@capacitor/cli` (via `tar`) — high

- **Advisory chain:** Multiple `tar` advisories (path traversal, symlink
  poisoning) bundled into `@capacitor/cli`'s transitive tree.
- **Current installed:** 6.2.1
- **Patched chain:** `@capacitor/cli@8.3.3+`
- **Status:** Deferred (folded into the Capacitor 6 → 8 stack upgrade above).

### Why deferred

`@capacitor/cli` is a **build-time-only** tool used by `npx cap sync ios`
during `./deploy/mobile/build-ios.sh`. It never runs in any deployed artefact
(server, web build, or iOS bundle). The `tar` advisories require an attacker
to feed a malicious tar archive into the CLI, which would only happen via the
local developer's `cap sync` pipeline; the attack surface is the developer's
workstation, not the production app.

Mitigation: bumping it requires the same Capacitor 6 → 8 stack upgrade as
the biometric plugin above. Wait for that coordinated effort.

---

## 3. `tar` < 7.6.0 — high (transitive only)

Already covered by §2. The only `tar` consumer in our tree is
`@capacitor/cli`. Direct usage zero. Will resolve when Capacitor stack
bumps.

---

## 4. `serialize-javascript` ≤ 7.0.4 — high (build-time-only, pinned)

- **Advisories:** [GHSA-5c6j-r48x-rmvq](https://github.com/advisories/GHSA-5c6j-r48x-rmvq) (RCE via `RegExp.flags`), [GHSA-qj8w-gfj5-8c6v](https://github.com/advisories/GHSA-qj8w-gfj5-8c6v) (DoS).
- **Current installed:** 6.0.2 (pinned via `package.json` `overrides`)
- **Patched version:** 7.0.5+ (requires Node 19+)
- **Status:** Deferred. Pinned at 6.0.2 to keep PWA service-worker generation
  working on the project's current Node toolchain.

### Why the patched version is blocked

`serialize-javascript@7.x` switched from the `uid-safe` polyfill to the Web
Crypto API (`crypto.getRandomValues`) at module-load time. In CommonJS the
unprefixed `crypto` identifier is only available as a global from Node 19+;
on Node 18 (which the current build toolchain runs), module load throws
`ReferenceError: crypto is not defined`. The only consumer in our tree is
`@rollup/plugin-terser` (via `workbox-build` → `vite-plugin-pwa`). Without a
working `serialize-javascript`, `npm run build` succeeds for the main bundle
but **silently fails to emit `sw.js` and `workbox-*.js`** — i.e. the PWA
offline cache is missing from production.

### Why the advisory's risk is low for us

The vulnerable code path requires attacker-controlled input to be passed to
`serialize-javascript.serialize()`. The only call site in our chain is
`@rollup/plugin-terser` serialising the workbox SW configuration. That config
is constructed entirely from `vite.config.js`, `vite-plugin-pwa` options, and
the precache manifest produced by our own build — there is no user-supplied
or network-fetched data in the build pipeline. The advisory is real for
runtime serialisation of untrusted data, but our build is the only consumer
and only its own config is passed in.

### Resolution path

Resolves when Node is bumped to ≥19 (which permits `serialize-javascript@7.x`
to load), at which point the pin can be removed and `@rollup/plugin-terser`
will pull 7.0.5+ automatically.

---

## 5. `vite` ≤ 6.4.1 — moderate (dev-server-only)

- **Advisory:** [GHSA-4w7w-66w2-5vf9](https://github.com/advisories/GHSA-4w7w-66w2-5vf9)
- **Current installed:** 5.4.x (constrained by `^5.0.0`)
- **Patched version:** 6.4.2 / 7.x / 8.0.12
- **Status:** Deferred.

### Why deferred

The vulnerability is a path-traversal in optimized-deps `.map` handling that
only manifests when running the Vite **dev server** with the unsanitised path
exposed to the network. Fynla's dev server binds to localhost only (
`vite.config.js` runs with `port: 5173, strictPort: true` and no `--host`
flag) — see memory `feedback_vite_canonical_port_5173.md`. The production
bundle is built ahead of time and served by Apache via the `RewriteBase`
configured per environment (see `deploy/fynla-org/build.sh` /
`deploy/csjones-fynla/build.sh`); Vite is **never** part of the production
serving stack.

### Why the upgrade is non-trivial

Vite 5 → 8 spans three major versions. The Capacitor + Vite + Laravel Mix
chain is hand-tuned for iOS WKWebView compatibility (see the
`vite.config.js` rules in `CLAUDE.md` "Mobile App (Capacitor iOS)" and the
PWA conditional in `resources/js/CLAUDE.md`). The Vite 6 → 7 jump in
particular changes how PWA plugins resolve, and Vite 8 dropped Node 18
support. Tracked alongside the Capacitor 8 migration ticket.

### Mitigation in the meantime

Don't expose the local Vite dev server (`./dev.sh`) on a network interface
other than `127.0.0.1`. If you ever pass `--host` or expose port 5173 on a
LAN, this advisory becomes live.

---

## Acknowledged, not deferred

The following non-breaking advisories were resolved on this branch by
`npm audit fix` (lockfile-only changes plus an explicit `axios` semver bump):

- `axios` 1.7.0 → 1.15.2+ (multiple high/moderate prototype-pollution and
  SSRF advisories) — `package.json` bumped from `^1.7.0` to `^1.15.2` so
  fresh installs are guaranteed safe.
- `@babel/plugin-transform-modules-systemjs` 7.12.0–7.29.0 → 7.29.3+ (arbitrary
  code generation)
- `fast-uri` <3.1.1 → patched (path traversal, host confusion)
- `postcss` <8.5.10 → patched (XSS via unescaped `</style>`)
`npm audit` after these fixes reports 8 entries (5 root packages — see
above), down from 11.
