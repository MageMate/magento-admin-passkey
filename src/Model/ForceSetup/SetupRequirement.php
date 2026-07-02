<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\ForceSetup;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\AdobeImsState;
use MageMate\AdminPasskey\Model\Config;

/**
 * Decides whether an admin user must be forced to register a passkey.
 *
 * Force-setup only applies when the feature is enabled, the `force_setup`
 * flag is on, Adobe IMS is not the active admin auth method (D6), and the
 * user owns no active, non-expired passkey.
 */
class SetupRequirement
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
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @param Config $config
     * @param AdobeImsState $adobeImsState
     * @param PasskeyRepositoryInterface $passkeyRepository
     */
    public function __construct(
        Config $config,
        AdobeImsState $adobeImsState,
        PasskeyRepositoryInterface $passkeyRepository
    ) {
        $this->config = $config;
        $this->adobeImsState = $adobeImsState;
        $this->passkeyRepository = $passkeyRepository;
    }

    /**
     * Whether the given admin user must register a passkey before proceeding.
     *
     * @param int $userId
     * @return bool
     */
    public function isRequiredFor(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!$this->config->isEnabled() || !$this->config->isForceSetup()) {
            return false;
        }

        // D6: passkey features are suppressed while Adobe IMS owns admin login.
        if ($this->adobeImsState->isActive()) {
            return false;
        }

        return !$this->passkeyRepository->hasActivePasskey($userId);
    }
}
