<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Login;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\FeatureAvailability;
use Magento\User\Model\UserFactory;

/**
 * Decides whether password sign-in must be blocked for an admin user.
 *
 * Password login is blocked only when the passkey feature is available (feature
 * on and Adobe IMS not the active admin auth method, per D6), the
 * `disallow_password_login` flag is on, and the user owns at least one active,
 * non-expired passkey. Users without an active passkey (or with only expired
 * ones) keep password login so they are never locked out by expiry alone.
 */
class PasswordLoginPolicy
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
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @param FeatureAvailability $featureAvailability
     * @param Config $config
     * @param UserFactory $userFactory
     * @param PasskeyRepositoryInterface $passkeyRepository
     */
    public function __construct(
        FeatureAvailability $featureAvailability,
        Config $config,
        UserFactory $userFactory,
        PasskeyRepositoryInterface $passkeyRepository
    ) {
        $this->featureAvailability = $featureAvailability;
        $this->config = $config;
        $this->userFactory = $userFactory;
        $this->passkeyRepository = $passkeyRepository;
    }

    /**
     * Whether password sign-in must be blocked for the given username.
     *
     * @param string $username
     * @return bool
     */
    public function isPasswordLoginBlocked(string $username): bool
    {
        // D6: isEnabled() is false while Adobe IMS owns admin login.
        if (!$this->featureAvailability->isEnabled() || !$this->config->isPasswordLoginDisallowed()) {
            return false;
        }

        if (trim($username) === '') {
            return false;
        }

        $user = $this->userFactory->create();
        $user->loadByUsername($username);
        $userId = (int) $user->getId();
        if ($userId <= 0) {
            return false;
        }

        return $this->passkeyRepository->hasActivePasskey($userId);
    }
}
