<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

/**
 * Single seam deciding whether the admin passkey feature is operative.
 *
 * Passkeys are only available when the feature is switched on in configuration
 * AND Adobe IMS is not the active admin authentication method: when IMS owns
 * admin login, all passkey behaviour auto-disables (decision D6). Every passkey
 * entry point (login button, registration/login ceremonies, force-setup and
 * password-blocking policies) gates on this method so the two conditions live
 * in one place.
 */
class FeatureAvailability
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var AdobeImsState
     */
    private AdobeImsState $adobeImsState;

    /**
     * @param Config $config
     * @param AdobeImsState $adobeImsState
     */
    public function __construct(Config $config, AdobeImsState $adobeImsState)
    {
        $this->config = $config;
        $this->adobeImsState = $adobeImsState;
    }

    /**
     * Whether admin passkey features are operative right now.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && !$this->adobeImsState->isActive();
    }

    /**
     * Whether passkeys are configured on but suppressed because IMS is active.
     *
     * Used to surface the admin notice explaining the auto-disable.
     *
     * @return bool
     */
    public function isSuppressedByAdobeIms(): bool
    {
        return $this->config->isEnabled() && $this->adobeImsState->isActive();
    }
}
