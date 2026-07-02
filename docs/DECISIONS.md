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
