# MageMate_AdminPasskey — Decisions

## D1 — WebAuthn library (resolved: hand-rolled adapter behind internal interfaces)

**Decision:** Implement a purpose-built, minimal WebAuthn verifier reusing
`2tvenom/cborencode` (CBOR decoding) plus PHP's native `openssl` for signature
verification, hidden behind internal interfaces so the rest of the module stays
library-agnostic. This resolves PRD open decision D1.

**Why not `web-auth/webauthn-lib` (the PRD recommendation):**

- This repository disables Packagist (`"packagist.org": false` in root
  `composer.json`); dependencies resolve only from the local `path` repo and the
  private `repo.packagist.com/falconmedia` composer repo. `web-auth/webauthn-lib`
  and its dependency tree are **not available** through either, so it cannot be
  installed here.
- `magento/module-two-factor-auth` already proves the hand-rolled CBOR + openssl
  approach works in this exact environment (its U2F engine uses
  `2tvenom/cborencode`, which is therefore already vendored).
- Keeping the crypto surface small and fully owned makes the security-critical
  verification path auditable and lets the unit tests exercise the real checks
  without an un-installable dependency.

**How library-agnosticism is preserved:** every concrete detail (CBOR decoding,
COSE→PEM conversion, authenticator-data parsing) lives in
`Model/Webauthn/Internal/*`. The rest of the module depends only on the
interfaces `RelyingPartyInterface`, `RegistrationOptionsFactoryInterface`,
`AssertionOptionsFactoryInterface`, `RegistrationVerifierInterface`,
`AssertionVerifierInterface` and the DTOs under `Model/Webauthn/Data`. Swapping
in `web-auth/webauthn-lib` later (should the repo's composer policy change) is a
matter of providing alternative implementations of those interfaces.

**Algorithm support:** ES256 (ECDSA P-256, COSE alg -7) and RS256
(RSASSA-PKCS1-v1_5 SHA-256, COSE alg -257). These cover effectively all shipping
platform and roaming authenticators. Unsupported COSE keys are rejected rather
than silently downgraded.

**Attestation policy:** `none` by default (privacy — see D5). The ceremony
binding (origin, RP id hash, challenge, user presence, user verification) and the
public-key extraction are enforced; the attestation statement itself is not
cryptographically verified under the `none` policy.

## D2 — Passwordless session establishment (resolved: mirror Adobe IMS `loginByUsername`, US-007)

**Decision:** After a passkey assertion verifies, establish the backend session
directly instead of routing through `Magento\Backend\Model\Auth::login` (which
requires a password). `Model\Login\AssertionAuthenticator::establishSession()`
mirrors `Magento\AdminAdobeIms\Model\Auth::loginByUsername`: `setUser($user)` →
`processLogin()` (regenerates the session id and renews secret URL keys) →
`$user->getResource()->recordLogin($user)` → dispatch
`backend_auth_user_login_success`. The admin action log and login event
subscribers therefore see the login exactly as they would for a password login.

**Why this is safe to bypass the password check:** the password is *replaced*, not
skipped — a phishing-resistant WebAuthn assertion (origin + RP-ID hash + single-use
challenge + signature + `signCount` monotonicity + UV) is verified server-side
before `setUser` is ever called. Every verification failure collapses to one
generic `WebauthnException` (anti-enumeration). The endpoints implement
`HttpPostActionInterface` + `CsrfAwareActionInterface` (not backend
`AbstractAction`) so they are reachable logged-out without opening any same-named
authenticated action.

**Not chosen:** faking a password / reflection into `Auth`, or a custom auth
adapter — both are more fragile than reusing core's own post-login sequence.

## D4 — Management location (resolved: dedicated adminhtml grid, US-006)

**Decision:** Ship a UI-component grid (`magemate_admin_passkey_listing`) under
**System → Other Settings → Admin Passkeys** to list / rename / delete passkeys,
plus the self-service **Register a passkey** page. Own-vs-all visibility is
enforced in two layers: the grid collection hides other users' rows unless
`MageMate_AdminPasskey::manage_all` is granted, and every mutating controller
re-checks per row via `Model\Management\AccessValidator::canManage()`.

**Why not a tab on the admin-user edit form (TFA's `AddTabToAdminUserEdit`
pattern):** no core "Security" admin *menu* exists (only the system-config *tab*),
and a standalone grid is the lower-coupling, self-service-friendly surface. The
admin-user-edit tab remains a possible future addition (block + layout update on
`adminhtml_user_edit`) but was not required to satisfy the story.

## D5 — Attestation policy (resolved: `none`, US-002)

**Decision:** Request `attestation: none`. The registration verifier enforces the
ceremony binding and extracts/stores the public key, but does not cryptographically
verify the attestation statement or trust an AAGUID. AAGUID is stored best-effort
only (privacy + §6).

**Why:** `none` avoids handling attestation CA chains and metadata, keeps the
crypto surface small and auditable, and matches the privacy posture in the brief.
The seam for a future `direct` policy is `$attestation['fmt']` / `attStmt` in
`RegistrationVerifier` — flipping to `direct` means adding an attestation-format
verifier there, nothing else changes.

## D7 — Challenge store for pre-auth (resolved: session-bound single-use store, US-007)

**Decision:** Store ceremony challenges in the Magento session (backend session
for authenticated registration via `Model\Registration\ChallengeStorage`, a
user-agnostic session store for pre-auth login via
`Model\Login\LoginChallengeStorage`). Each challenge is **single-use** — read and
cleared before verification — and the registration challenge is additionally pinned
to the `user_id` to reject cross-user replay. The pre-auth options endpoint is
rate-limited (`Model\Login\RateLimiter`, coarse per-IP sliding window via
`CacheInterface` + `RemoteAddress`).

**Why not cookie-signed challenges:** a server-side single-use store gives true
one-shot semantics (a signed cookie can be replayed until its TTL) and needs no
extra signing-key management. Magento sessions are already Redis-backed in
production, satisfying the D7 "short-TTL store" intent without a bespoke cache
schema. The seam is small: swapping to a dedicated `CacheInterface` store later is
a drop-in change behind the same storage classes.

## D9 — Minimum Magento / PHP (resolved: repo baseline, US-001/US-013)

**Decision:** Target the repository's own baseline — Magento 2.4.8-p2 / PHP 8.3 —
rather than pinning a `web-auth/webauthn-lib` version (D1 chose the hand-rolled
adapter, so there is no external WebAuthn library to version). Runtime requirements
are `ext-json` + `ext-openssl` (native) and `2tvenom/cborencode` (already vendored
via `magento/module-two-factor-auth`). No new third-party dependency is introduced,
so there is no external compatibility matrix to maintain.

## D3 / D6 — TFA and Adobe IMS reconciliation (resolved: 2FA grant + IMS auto-disable, US-011)

**D3 — passkey as second factor.** When `satisfies_2fa` is on, a successful
passwordless passkey login marks Magento's Two-Factor Auth session granted, so
`Magento\TwoFactorAuth\Observer\ControllerActionPredispatch` no longer challenges
(or forces provider configuration for) that admin — the phishing-resistant
passkey stands in for the configured second factor. When the flag is off the 2FA
session is left untouched and the admin completes their configured provider as
usual.

The grant is written by `Model\Tfa\TwoFactorAuthBridge::grantIfPasskeySatisfiesTwoFactor()`,
called from `AssertionAuthenticator::establishSession()` **after** `processLogin()`
so it survives the session-id regeneration. `Magento_TwoFactorAuth` is a *soft*
dependency (module.xml `<sequence>`), so the bridge resolves
`Magento\TwoFactorAuth\Api\TfaSessionInterface` lazily via the object manager and
guards with `interface_exists()` — the module keeps working when TFA is absent
(referring to the interface via `::class` does not autoload it). No conflict with
TFA's predispatch observer: our own `Observer\ForcePasskeySetup` already sequences
after TFA and allow-lists `tfa_*` actions, and granting the session means TFA's
observer simply sees `isGranted() === true` and returns without redirecting (no
loop, no fight over the redirect).

**D6 — Adobe IMS auto-disable.** All passkey features auto-disable while Adobe
IMS owns admin login. `Model\FeatureAvailability::isEnabled()` is the single seam
(`Config::isEnabled() && !AdobeImsState::isActive()`); every entry point — the
login button, the login/registration options endpoints, force-setup and
password-blocking policies — gates on it, so IMS-active means no passkey button,
no ceremonies, no forced setup, no password block. `AdobeImsState` reads the
`adobe_ims/integration/admin_enabled` config path rather than depending on the
Adobe IMS module, so detection works whether or not that module is installed.
`Model\System\Message\AdobeImsConflict` surfaces a minor admin notification when
passkeys are switched on but suppressed by IMS, explaining why the feature is
inactive.

**Not chosen:** hard-injecting `TfaSessionInterface`/`ImsConfig` (would break the
module when either optional module is absent), and re-implementing a 2FA provider
(the session-grant approach reuses TFA's own bypass path instead of adding a
provider the admin would have to configure).

## D8 — Lockout / recovery (resolved: super-admin CLI deactivates the user's passkeys)

**Decision:** Recovery is a console command run by someone with shell (super-admin)
access:

```
bin/magento security:passkey:recover <username>
```

It deactivates every passkey owned by that admin user. Because password login is
blocked *only* while the user owns an **active** passkey (see below), deactivating
them re-opens password sign-in for that user immediately — no config change, no
downtime for other admins, no emergency global bypass window.

**How password login is blocked (US-009):** a `before` plugin on
`Magento\Backend\Model\Auth::login` (`Plugin\Backend\DisallowPasswordLogin`)
delegates to `Model\Login\PasswordLoginPolicy::isPasswordLoginBlocked($username)`.
It throws `AuthenticationException` (directing the user to the passkey button)
only when **all** hold: the feature is enabled, `disallow_password_login` is on,
Adobe IMS is not the active admin auth (D6), the username resolves to a real admin
user, and that user owns ≥1 **active, non-expired** passkey
(`PasskeyRepository::hasActivePasskey`).

**Why this design avoids permanent lockout:**

- **Expiry never locks anyone out.** `hasActivePasskey` excludes expired
  credentials, so if a user's only passkeys expire, the block lifts automatically
  and password login works again — the exact scenario D8 worried about is a no-op.
- **Lost/broken authenticator** (user still has an active passkey but can't use
  it) is the one true lockout, and that is what the CLI recovers: a super-admin
  deactivates the stuck credentials and the user falls back to password login,
  after which they can re-register a passkey.
- **Blast radius is one user.** Unlike flipping the global
  `disallow_password_login` config off, the CLI touches only the named user, so
  the policy stays enforced for everyone else.
- **The plugin runs before `Auth::login`'s own try/catch**, so its exception
  propagates cleanly to the login controller; the passwordless login path
  (US-007) bypasses `Auth::login` entirely and is unaffected.

**Not chosen:** a timed "emergency password bypass window" (adds a global
weakening with a race window) and a config-only toggle as the *primary* recovery
(too coarse — disables the policy site-wide). `bin/magento config:set
adminpasskey/general/disallow_password_login 0` remains available as a manual
last-resort global override, but the per-user CLI is the documented path.
