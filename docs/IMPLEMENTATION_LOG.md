# MageMate_AdminPasskey — Implementation Log

Chronological record of the passkey feature build. Each entry maps to one user
story (US-001 … US-013) and its commit. Decisions are recorded separately in
[`DECISIONS.md`](DECISIONS.md); QA gate results in [`QA_REPORT.md`](QA_REPORT.md).

## 2026-07-02 — US-001 Module scaffold & composer wiring (`bc8d9c0`)
- Scaffolded `MageMate_AdminPasskey` at `package-source/magemate/magento-admin-passkey`.
- `composer.json` (`magento2-module`, PSR-4 `MageMate\AdminPasskey\` → `src/`,
  autoload-dev `…\Test\` → `tests/`), `src/registration.php`, `src/etc/module.xml`
  (`<sequence>`: Backend, User, Authorization, Config, Ui).
- Wired into root `composer.json` `require`; symlinked via the existing `path` repo.
- Module root = `src/` because `registration.php` uses `__DIR__`, so `etc/` lives
  under `src/etc/`.

## 2026-07-02 — US-002 WebAuthn engine & abstraction (`b038b2e`)
- Resolved D1 (hand-rolled adapter — Packagist disabled, `web-auth/webauthn-lib`
  not installable). Library-agnostic WebAuthn layer under `src/Model/Webauthn/`:
  `RelyingParty`, `RegistrationOptionsFactory`, `AssertionOptionsFactory`,
  `RegistrationVerifier`, `AssertionVerifier` (interfaces + impls), `Internal/*`
  adapters (Base64Url, CBOR decode via `2tvenom`, COSE→PEM, authenticator-data
  parser, client-data validator, challenge generator), readonly `Data/*` DTOs.
- Enforces origin, RP-ID hash, single-use challenge (constant-time), UV flag, user
  presence, ES256/RS256 signature, monotonic `signCount` (clone detection).

## 2026-07-02 — US-003 Persistence — table & service contracts (`d8aefb9`)
- Declarative schema `magemate_admin_passkey` (`src/etc/db_schema.xml` +
  whitelist): unique `credential_id`, FK cascade to `admin_user.user_id`, index on
  `user_id`.
- Service contracts: `Api/Data/PasskeyInterface`, `…/PasskeySearchResultsInterface`,
  `Api/PasskeyRepositoryInterface`; Model / ResourceModel / Collection / Repository;
  `di.xml` preferences. Repository enforces credential-id uniqueness and
  active + non-expired reads (`getActiveByCredentialId`).

## 2026-07-02 — US-004 ACL & system configuration (`ad3bbed`)
- `etc/acl.xml` (`manage_own` > `manage_all`, `config`); `etc/adminhtml/system.xml`
  (Security tab, `adminpasskey/general` section — all §5.7 fields); `etc/config.xml`
  security-first defaults; typed `Model/Config` reader.

## 2026-07-02 — US-005 Passkey registration (authenticated) (`cf84990`)
- `passkey/register/{index,options,verify}` controllers, session-bound single-use
  challenge (`ChallengeStorage`), `ExpiryResolver`, `CredentialRegistrar`
  (verify → dedupe → persist), RequireJS `navigator.credentials.create()` ceremony.

## 2026-07-02 — US-006 Passkey management UI (`ea899aa`)
- UI-component grid `magemate_admin_passkey_listing` (derived `status`, admin-user
  join, owner restriction), inline rename, delete / mass-delete, row actions,
  `Model/Management/AccessValidator` own-vs-all guard on every mutation.

## 2026-07-02 — US-007 Passwordless login + assertion ceremony (`a1fd58a`)
- "Sign in with a passkey" button + `passkey/login/{options,verify}` (anonymous,
  rate-limited, `HttpPostActionInterface` + `CsrfAwareActionInterface`),
  `LoginChallengeStorage`, `RateLimiter`, `AssertionAuthenticator` (resolves
  credential → owner → verifies → advances `sign_count`/`last_used_at` →
  establishes backend session, D2 path). `navigator.credentials.get()` ceremony.

## 2026-07-02 — US-008 Force passkey setup after login (`f07f548`)
- `controller_action_predispatch` observer redirects admins with no active passkey
  to `passkey/register` when `force_setup` is on. Sequences after
  `Magento_TwoFactorAuth`; allow-lists `passkey_*` / `tfa_*` / auth actions.
  `SetupRequirement`, `AdobeImsState`, `PasskeyRepository::hasActivePasskey`.

## 2026-07-02 — US-009 Disallow password login (`0d85640`)
- `before` plugin on `Magento\Backend\Model\Auth::login` throws
  `AuthenticationException` when `disallow_password_login` is on and the user owns
  an active passkey (`PasswordLoginPolicy`). D8 recovery CLI
  `security:passkey:recover <username>` deactivates a user's passkeys to re-open
  password login.

## 2026-07-02 — US-010 Passkey max validity / expiry (`f8e15a5`)
- Verified expiry across the stack (resolver at registration, verifier rejects
  expired, grid flags expired, `0` = no expiry); added the grid "Re-register" row
  action for the current admin's own expired passkeys.

## 2026-07-02 — US-011 TFA / Adobe IMS integration (`a62a67a`)
- `satisfies_2fa` → `TwoFactorAuthBridge` grants the TFA session after login (soft
  dep). `Model/FeatureAvailability` = single gate (`isEnabled && !imsActive`) for
  every entry point; `Model/System/Message/AdobeImsConflict` admin notice when
  passkeys are on but IMS-suppressed.

## 2026-07-02 — US-012 Integration tests (written, not executed) (`8095217`)
- `tests/Integration/**` covering registration/dedupe, assertion resolution +
  tamper/expiry/replay rejection, force-setup redirect, disallow-password policy,
  repository + config reader. Statically validated only (integration env broken per
  PRD); designed for Magento's `dev/tests/integration` runner.

## 2026-07-02 — US-013 Docs, QA, standards pass
- README expanded with security notes; `DECISIONS.md` completed (D2/D4/D5/D7/D9
  added to the existing D1/D3/D6/D8); this log created; `QA_REPORT.md` created with
  the final gate results (PHPCS 0 errors, PHPMD documented false-positives only,
  `setup:upgrade` + `setup:di:compile` clean, 91 unit tests green). §8 manual E2E
  recorded as an environment limitation (headless — no WebAuthn authenticator).
