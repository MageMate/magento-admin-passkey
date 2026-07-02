# MageMate_AdminPasskey

Passwordless / WebAuthn (FIDO2 **passkey**) authentication for the Magento 2 backend (admin).

## Overview

`MageMate_AdminPasskey` adds passkey authentication to the Magento admin. Admin
users can register one or more passkeys after a normal login and manage them
(list, rename, delete), then sign in with a **"Sign in with a passkey"** button
on the admin login form via the browser WebAuthn API (discoverable credentials —
no username typing).

Store owners can, via System Configuration (`Security` tab → `adminpasskey`
section):

- **Force** users to set up a passkey after a normal login.
- Set a **maximum validity** (lifetime) for a passkey.
- **Disallow normal (password) login** for users who already have an active passkey.

Security-first is the guiding principle — this module touches admin
authentication, the highest-value attack surface in the store.

## Installation

The module lives at `package-source/magemate/magento-admin-passkey` and is wired
into the root `composer.json` as a `path` repository.

```bash
composer update magemate/magento-admin-passkey
bin/magento module:enable MageMate_AdminPasskey
bin/magento setup:upgrade
bin/magento setup:di:compile   # required — registers the CLI command + interceptors
bin/magento cache:flush
```

The module is **disabled on first install** — enable it explicitly. After adding
or changing DI (plugins, console commands) re-run `setup:di:compile`; a compiled
env will not surface new wiring on `cache:flush` alone.

## Configuration

**Stores → Configuration → Security → Passkey** (`adminpasskey/general`). All
defaults are security-first (feature off, UV required):

| Field | Path | Default | Effect |
| --- | --- | --- | --- |
| Enable passkey login | `enabled` | `0` | Master switch for every passkey entry point. |
| Force passkey setup after login | `force_setup` | `0` | Redirect admins with no active passkey to the register page. |
| Disallow password login | `disallow_password_login` | `0` | Block password sign-in for admins who own an active passkey. |
| Max passkey validity (days) | `passkey_max_validity_days` | `0` | Sets `expires_at` at registration; `0` = never expires. |
| Require user verification | `require_user_verification` | `1` | Enforce the WebAuthn `UV` flag (PIN/biometric). |
| Passkey satisfies 2FA | `satisfies_2fa` | `1` | A passkey login grants the Two-Factor-Auth session. |
| Relying Party ID / Name | `rp_id` / `rp_name` | derived | Override the WebAuthn RP id/name (defaults from the admin base URL). |

### Usage
- **Register**: System → Other Settings → *Register a passkey* (after a normal login).
- **Manage**: System → Other Settings → *Admin Passkeys* (list / rename / delete;
  own passkeys always, all users' passkeys with `manage_all`).
- **Sign in**: the **Sign in with a passkey** button on the admin login form
  (discoverable credential — no username typed).
- **Recovery (lockout)**: `bin/magento security:passkey:recover <username>`
  deactivates that admin's passkeys, re-opening password login for them.

## Security notes

This module is on the admin authentication path — the store's highest-value attack
surface. The security posture (see PRD §6 and [`docs/DECISIONS.md`](docs/DECISIONS.md)):

- **Ceremony verification (server-side).** Every assertion/attestation is checked
  for origin, RP-ID hash, single-use challenge, WebAuthn `UP`/`UV` flags, and an
  ES256/RS256 signature. `signCount` must increase monotonically — a non-increasing
  count is treated as a cloned authenticator and rejected.
- **Anti-enumeration.** Passwordless login uses **discoverable credentials** (no
  username is typed or leaked). Every verification failure collapses to one generic
  `WebauthnException` with uniform response shape, so the endpoints never reveal
  whether a user or credential exists.
- **Single-use, bound challenges.** Challenges are stored server-side, read-and-
  cleared before verification (one shot). The registration challenge is pinned to
  the `user_id`; the pre-auth login options endpoint is rate-limited per IP.
- **CSRF.** Authenticated endpoints use Magento form keys; the pre-auth endpoints
  are protected by the single-use server challenge binding + rate limiting.
- **Key storage.** Only **public** keys are stored — never private key material.
  Credential IDs are unique (DB constraint) and `expires_at` is enforced on every
  read path. AAGUID is stored best-effort only (`attestation: none`, privacy).
- **No policy weakening by default.** Password and TFA policy are only relaxed when
  explicitly configured (`disallow_password_login`, `satisfies_2fa`). Lost/broken
  authenticators are recovered per-user via the CLI, never a global bypass window.
- **Adobe IMS.** When Adobe IMS owns admin login, all passkey features
  auto-disable (single `FeatureAvailability` gate) and an admin notice explains why.
- **No secrets in logs.** Login attempts are recorded via the standard
  `backend_auth_user_login_*` events / admin action log; no key material is logged.

## Documentation
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — design decisions D1–D9.
- [`docs/IMPLEMENTATION_LOG.md`](docs/IMPLEMENTATION_LOG.md) — per-story build log.
- [`docs/QA_REPORT.md`](docs/QA_REPORT.md) — quality-gate results.

## Layout

```
magento-admin-passkey/
├── composer.json          # magemate/magento-admin-passkey (magento2-module)
├── README.md
├── docs/                  # DECISIONS.md, IMPLEMENTATION_LOG.md, QA_REPORT.md
├── src/                   # PSR-4 MageMate\AdminPasskey\
│   ├── registration.php   # registers MageMate_AdminPasskey
│   ├── etc/               # module.xml, di.xml, acl.xml, db_schema, system.xml, …
│   ├── Api/ Model/ Controller/ Block/ Observer/ Plugin/ Console/ Ui/
│   └── view/adminhtml/    # layout, templates, ui_component, web/js (ceremonies)
└── tests/                 # PSR-4 MageMate\AdminPasskey\Test\ (Unit + Integration)
```

## Module dependencies

Declared via `<sequence>` in `src/etc/module.xml`:

- `Magento_Backend`
- `Magento_User`
- `Magento_Authorization`
- `Magento_Config`
- `Magento_Ui`

## Development

- PHP 8.3, PSR-12, Magento 2 coding standards + PHPMD ruleset.
- Constructor DI only — no `new ClassName()` in app flow, no hand-written factories.
- Never edit core; integrate via plugins / observers / preferences.
- Integration tests live inside the module (`tests/Integration`).
