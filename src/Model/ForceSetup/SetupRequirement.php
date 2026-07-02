<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\ForceSetup;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\FeatureAvailability;

/**
 * Decides whether an admin user must be forced to register a passkey.
 *
 * Force-setup only applies when the passkey feature is available (feature on
 * and Adobe IMS not the active admin auth method, per D6), the `force_setup`
 * flag is on, and the user owns no active, non-expired passkey.
 */
class SetupRequirement
{
    /**
     * @var FeatureAvailability
     */
    private FeatureAvailability $featureAvailability;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @param FeatureAvailability $featureAvailability
     * @param Config $config
     * @param PasskeyRepositoryInterface $passkeyRepository
     */
    public function __construct(
        FeatureAvailability $featureAvailability,
        Config $config,
        PasskeyRepositoryInterface $passkeyRepository
    ) {
        $this->featureAvailability = $featureAvailability;
        $this->config = $config;
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

        // D6: isEnabled() is false while Adobe IMS owns admin login.
        if (!$this->featureAvailability->isEnabled() || !$this->config->isForceSetup()) {
            return false;
        }

        return !$this->passkeyRepository->hasActivePasskey($userId);
    }
}
