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
```

## Layout

```
magento-admin-passkey/
├── composer.json          # magemate/magento-admin-passkey (magento2-module)
├── README.md
├── src/                   # PSR-4 MageMate\AdminPasskey\
│   ├── registration.php   # registers MageMate_AdminPasskey
│   └── etc/
│       └── module.xml     # module declaration + sequence
└── tests/                 # PSR-4 MageMate\AdminPasskey\Test\ (autoload-dev)
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
