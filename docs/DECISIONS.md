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
