<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Management;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\AuthorizationInterface;

/**
 * Decides whether the current admin may manage a given passkey.
 *
 * Own passkeys require manage_own; passkeys owned by another admin require manage_all.
 */
class AccessValidator
{
    private const RESOURCE_MANAGE_OWN = 'MageMate_AdminPasskey::manage_own';
    private const RESOURCE_MANAGE_ALL = 'MageMate_AdminPasskey::manage_all';

    /**
     * @var AuthSession
     */
    private AuthSession $authSession;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @param AuthSession $authSession
     * @param AuthorizationInterface $authorization
     */
    public function __construct(AuthSession $authSession, AuthorizationInterface $authorization)
    {
        $this->authSession = $authSession;
        $this->authorization = $authorization;
    }

    /**
     * Whether the current admin may manage (rename/delete) the given passkey.
     *
     * @param PasskeyInterface $passkey
     * @return bool
     */
    public function canManage(PasskeyInterface $passkey): bool
    {
        $user = $this->authSession->getUser();
        $currentUserId = $user !== null ? (int)$user->getId() : 0;

        if ($currentUserId > 0
            && (int)$passkey->getUserId() === $currentUserId
            && $this->authorization->isAllowed(self::RESOURCE_MANAGE_OWN)
        ) {
            return true;
        }

        return $this->authorization->isAllowed(self::RESOURCE_MANAGE_ALL);
    }
}
