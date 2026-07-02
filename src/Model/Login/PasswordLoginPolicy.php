<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Login;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\AdobeImsState;
use MageMate\AdminPasskey\Model\Config;
use Magento\User\Model\UserFactory;

/**
 * Decides whether password sign-in must be blocked for an admin user.
 *
 * Password login is blocked only when the feature is enabled, the
 * `disallow_password_login` flag is on, Adobe IMS is not the active admin auth
 * method (D6), and the user owns at least one active, non-expired passkey.
 * Users without an active passkey (or with only expired ones) keep password
 * login so they are never locked out by expiry alone.
 */
class PasswordLoginPolicy
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
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @param Config $config
     * @param AdobeImsState $adobeImsState
     * @param UserFactory $userFactory
     * @param PasskeyRepositoryInterface $passkeyRepository
     */
    public function __construct(
        Config $config,
        AdobeImsState $adobeImsState,
        UserFactory $userFactory,
        PasskeyRepositoryInterface $passkeyRepository
    ) {
        $this->config = $config;
        $this->adobeImsState = $adobeImsState;
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
        if (!$this->config->isEnabled() || !$this->config->isPasswordLoginDisallowed()) {
            return false;
        }

        // D6: passkey policy is suppressed while Adobe IMS owns admin login.
        if ($this->adobeImsState->isActive()) {
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
