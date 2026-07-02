# MageMate_AdminPasskey — QA Report

Final quality-gate run for the passkey module (US-013). All commands were run from
the repository root against `package-source/magemate/magento-admin-passkey`.

## 2026-07-02 — Final standards pass (US-013)

### PHPCS — Magento2 coding standard
```
vendor/bin/phpcs --standard=Magento2 package-source/magemate/magento-admin-passkey/src
```
**Result: 0 errors**, 66 warnings across 9 files (all "Missing short description" /
tag-spacing docblock warnings on constructor-promoted readonly DTO properties in
`Model/Webauthn/Data/*` and getter-only interfaces). Per the module quality-gate
policy the target is **0 errors**; these getter/DTO docblock warnings are the
tolerated baseline (the Magento standard emits them for property-promotion
docblocks). Error-only re-run (`--warning-severity=0`) reports nothing.

### PHPMD — cleancode, codesize, unusedcode
```
vendor/bin/phpmd analyze --format text --ruleset cleancode --ruleset codesize \
  --ruleset unusedcode package-source/magemate/magento-admin-passkey/src
```
**Result: no real findings.** Three violations reported, all documented
false-positives for this codebase (no Magento PHPMD ruleset is vendored, so the
vanilla rulesets are stricter than Magento's):
- `Api/PasskeyRepositoryInterface::getListByUserId` + `Model/PasskeyRepository::getListByUserId`
  — `BooleanArgumentFlag` on `$activeOnly`. Intentional read-filter flag on a query
  method; not a SRP violation here.
- `Model/Webauthn/Internal/CborDecoder::decode` — `StaticAccess` on
  `\CBOR\CBOREncoder`. The vendored CBOR library exposes only static methods; this
  is isolated in the single `Internal` adapter by design (D1).

No `UnusedFormalParameter`, `unusedcode`, `ExcessiveParameterList`, or other
actionable findings.

### PHP / XML syntax
- `php -l` clean on all 64 `src` PHP files.
- `xmllint --noout` clean on all 14 `src` XML files.

### Unit tests
```
vendor/bin/phpunit -c package-source/magemate/magento-admin-passkey/phpunit.xml.dist
```
**Result: OK (91 tests, 167 assertions).** Covers the WebAuthn verifiers with real
EC/RSA crypto fixtures, registration/dedupe, assertion authentication, rate limiter,
challenge storage, force-setup requirement, password-login policy, feature
availability, the TFA bridge, the IMS conflict message, the recovery CLI, and the
grid access validator / row-action gating.

### Magento CLI gates (§8 "done")
```
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento module:status MageMate_AdminPasskey
```
- `setup:upgrade` — **OK**. `Module 'MageMate_AdminPasskey':` present in the schema
  pass; `magemate_admin_passkey` table created/current.
- `setup:di:compile` — **OK**. "Generated code and dependency injection
  configuration successfully." Validates every preference, plugin (`Auth::login`),
  observer, console command, and UI-component collection wiring.
- `module:status` — **Module is enabled**.
- Config visible under **Stores → Configuration → Security → Passkey**
  (`adminpasskey/general`, US-004; verified via the OM/scopeConfig probe — defaults
  do not appear in `config:show` until overridden).

## Manual end-to-end (§8) — environment limitation

§8 asks for a browser end-to-end: register a passkey, log out, sign in with the
passkey button, then manage/delete it, and confirm force-setup /
disallow-password / max-validity behave per config.

**Not executed — this is a headless environment with no WebAuthn authenticator**
(no platform TPM/Touch-ID or roaming key, no interactive browser), so
`navigator.credentials.create()` / `.get()` cannot run and a genuine assertion
cannot be produced. The same env also has `Magento_AdminAdobeIms` +
`Magento_TwoFactorAuth` enabled (though unconfigured), which the D6 gate would
auto-disable passkeys under if IMS were actually active.

**How the E2E behaviour is covered instead:**
- **Registration stores a credential / duplicates rejected**, and **assertion
  resolves the right user and rejects tampered / expired / replayed assertions** —
  covered by the integration tests (US-012) built on *real* EC/CBOR ceremonies
  against the live `RelyingParty`/`RegistrationVerifier`/`AssertionVerifier`, plus
  the 91 unit tests. These exercise the exact server-side verification the browser
  ceremony would drive.
- **Force-setup redirect** — `Controller/Adminhtml/ForceSetupRedirectTest`
  (functional, `AbstractBackendController`) asserts the redirect to
  `passkey/register` and that the register route / force-off case are not
  redirected.
- **Disallow-password / max-validity / TFA / IMS behaviour** — decision seams
  (`PasswordLoginPolicy`, `ExpiryResolver`, `FeatureAvailability`,
  `TwoFactorAuthBridge`, `SetupRequirement`) are unit-tested for every branch.
- **Controller wiring / route reachability** — validated by `setup:di:compile`
  (interceptor generation) and OM resolution; the login controllers are confirmed
  `HttpPostActionInterface` + `CsrfAwareActionInterface` (reachable logged-out).

**To complete the manual E2E on a real host** (with a platform/roaming
authenticator, passkeys enabled, and Adobe IMS admin login off):
1. Enable `adminpasskey/general/enabled`; log in with a password.
2. Open **System → Other Settings → Register a passkey**, complete the browser
   prompt → the credential appears in **Admin Passkeys**.
3. Log out; on the admin login form click **Sign in with a passkey** → the browser
   offers the discoverable credential → land on the dashboard.
4. In **Admin Passkeys**, rename and delete the credential.
5. Toggle `force_setup`, `disallow_password_login`, and
   `passkey_max_validity_days` and confirm the redirect / password block / expiry
   flag behave per config. Recovery: `bin/magento security:passkey:recover <user>`.
