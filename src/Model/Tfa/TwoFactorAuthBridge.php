<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Tfa;

use MageMate\AdminPasskey\Model\Config;
use Magento\Framework\ObjectManagerInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;

/**
 * Bridges a successful passkey login to Magento's Two-Factor Auth session.
 *
 * When `satisfies_2fa` is on, a passwordless passkey login marks the 2FA
 * session as granted so {@see \Magento\TwoFactorAuth\Observer\ControllerActionPredispatch}
 * does not challenge the admin again — the passkey (a phishing-resistant second
 * factor) stands in for the configured provider. When the flag is off, the
 * session is left untouched and the admin completes their configured 2FA.
 *
 * `Magento_TwoFactorAuth` is a soft dependency (module.xml sequence), so the
 * TFA service is resolved lazily and only when the module is present. Referring
 * to {@see TfaSessionInterface} via `::class` does not autoload it, and the
 * interface_exists() guard keeps the module working when TFA is absent.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TwoFactorAuthBridge
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @param Config $config
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(Config $config, ObjectManagerInterface $objectManager)
    {
        $this->config = $config;
        $this->objectManager = $objectManager;
    }

    /**
     * Mark the 2FA session granted when a passkey login satisfies two-factor.
     *
     * No-op when `satisfies_2fa` is off (the admin still completes 2FA) or when
     * the Two-Factor Auth module is not installed.
     *
     * @return void
     */
    public function grantIfPasskeySatisfiesTwoFactor(): void
    {
        if (!$this->config->satisfiesTwoFactor()) {
            return;
        }

        if (!interface_exists(TfaSessionInterface::class)) {
            return;
        }

        /** @var TfaSessionInterface $tfaSession */
        $tfaSession = $this->objectManager->get(TfaSessionInterface::class);
        $tfaSession->grantAccess();
    }
}
