# Tests

- `Unit/` — PHPUnit unit tests (run via `vendor/bin/phpunit -c package-source/magemate/magento-admin-passkey/phpunit.xml.dist`).
- `Integration/` — Magento integration tests (PSR-4 `MageMate\AdminPasskey\Test\Integration\`).

Do **not** run the integration tests here — the env is broken. Write them only.
They target Magento's `dev/tests/integration` framework
(`Magento\TestFramework\Helper\Bootstrap`) and use `@magentoDbIsolation`,
`@magentoAppArea` and `@magentoConfigFixture` annotations, so they roll back all
fixtures and never mutate persistent state.
