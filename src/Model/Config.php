<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed reader for the admin passkey system configuration.
 *
 * Centralises access to the `adminpasskey/general/*` paths defined in
 * system.xml/config.xml so the rest of the module never touches raw
 * configuration values. Defaults are security-first.
 */
class Config
{
    /**
     * Whether admin passkeys are enabled at all.
     */
    public const XML_PATH_ENABLED = 'adminpasskey/general/enabled';

    /**
     * Whether admins without a passkey are forced to register one after login.
     */
    public const XML_PATH_FORCE_SETUP = 'adminpasskey/general/force_setup';

    /**
     * Whether password login is blocked for admins who have a passkey.
     */
    public const XML_PATH_DISALLOW_PASSWORD_LOGIN = 'adminpasskey/general/disallow_password_login';

    /**
     * Passkey validity window in days (0 = no expiry).
     */
    public const XML_PATH_MAX_VALIDITY_DAYS = 'adminpasskey/general/passkey_max_validity_days';

    /**
     * Whether user verification must be enforced during ceremonies.
     */
    public const XML_PATH_REQUIRE_USER_VERIFICATION = 'adminpasskey/general/require_user_verification';

    /**
     * Whether a passkey login satisfies the two-factor requirement.
     */
    public const XML_PATH_SATISFIES_2FA = 'adminpasskey/general/satisfies_2fa';

    /**
     * Optional relying party ID override.
     */
    public const XML_PATH_RP_ID = 'adminpasskey/general/rp_id';

    /**
     * Optional relying party name override.
     */
    public const XML_PATH_RP_NAME = 'adminpasskey/general/rp_name';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Whether admin passkey support is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether admins without a passkey must register one after signing in.
     *
     * @return bool
     */
    public function isForceSetup(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FORCE_SETUP, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether password login is disallowed for admins who own a passkey.
     *
     * @return bool
     */
    public function isPasswordLoginDisallowed(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DISALLOW_PASSWORD_LOGIN,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Passkey validity window in days; 0 means passkeys never expire.
     *
     * @return int
     */
    public function getMaxValidityDays(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_VALIDITY_DAYS,
            ScopeInterface::SCOPE_STORE
        ));
    }

    /**
     * Whether user verification (biometric/PIN) must be enforced.
     *
     * Defaults to true when the path is unset (security-first).
     *
     * @return bool
     */
    public function isUserVerificationRequired(): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_REQUIRE_USER_VERIFICATION,
            ScopeInterface::SCOPE_STORE
        );
        if ($value === null) {
            return true;
        }

        return (bool)(int)$value;
    }

    /**
     * Whether a successful passkey login satisfies two-factor authentication.
     *
     * Defaults to true when the path is unset.
     *
     * @return bool
     */
    public function satisfiesTwoFactor(): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_SATISFIES_2FA, ScopeInterface::SCOPE_STORE);
        if ($value === null) {
            return true;
        }

        return (bool)(int)$value;
    }

    /**
     * Configured relying party ID override, or null when derived.
     *
     * @return string|null
     */
    public function getRpId(): ?string
    {
        $value = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RP_ID, ScopeInterface::SCOPE_STORE));

        return $value === '' ? null : $value;
    }

    /**
     * Configured relying party name override, or null when derived.
     *
     * @return string|null
     */
    public function getRpName(): ?string
    {
        $value = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RP_NAME, ScopeInterface::SCOPE_STORE));

        return $value === '' ? null : $value;
    }
}
