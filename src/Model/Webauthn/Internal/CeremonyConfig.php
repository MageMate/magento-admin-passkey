<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Minimal ceremony configuration accessor.
 *
 * Only the values needed by the WebAuthn service layer are exposed here; the
 * full typed configuration reader is introduced with the system configuration
 * story. Defaults are security-first (user verification required).
 */
class CeremonyConfig
{
    /**
     * Configuration path for the user-verification requirement toggle.
     */
    public const XML_PATH_REQUIRE_UV = 'adminpasskey/general/require_user_verification';

    /**
     * Ceremony timeout in milliseconds.
     */
    private const CEREMONY_TIMEOUT_MS = 60000;

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
     * Whether user verification (biometric/PIN) must be enforced.
     *
     * Defaults to true when the configuration path is not yet defined.
     *
     * @return bool
     */
    public function isUserVerificationRequired(): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_REQUIRE_UV, ScopeInterface::SCOPE_STORE);
        if ($value === null) {
            return true;
        }

        return (bool)(int)$value;
    }

    /**
     * WebAuthn userVerification requirement string for ceremony options.
     *
     * @return string
     */
    public function getUserVerificationRequirement(): string
    {
        return $this->isUserVerificationRequired() ? 'required' : 'preferred';
    }

    /**
     * Ceremony timeout in milliseconds.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return self::CEREMONY_TIMEOUT_MS;
    }
}
