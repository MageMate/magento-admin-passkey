<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\Data\PasskeyInterfaceFactory;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/**
 * Creates admin users and passkey rows for the integration tests.
 *
 * All records are created through the real object manager so database
 * isolation (@magentoDbIsolation enabled) rolls them back after each test.
 */
trait PasskeyFixtureTrait
{
    /**
     * Create and persist an active admin user with a unique username/email.
     *
     * @param string $suffix
     * @return User
     */
    private function createAdminUser(string $suffix): User
    {
        /** @var UserFactory $userFactory */
        $userFactory = Bootstrap::getObjectManager()->get(UserFactory::class);

        /** @var User $user */
        $user = $userFactory->create();
        $user->setData([
            'username' => 'passkey_' . $suffix,
            'firstname' => 'Passkey',
            'lastname' => 'Tester',
            'email' => 'passkey_' . $suffix . '@example.com',
            'password' => 'Passkey123!',
            'interface_locale' => 'en_US',
            'is_active' => 1,
        ]);
        $user->save();

        return $user;
    }

    /**
     * Persist a passkey row for the given admin user.
     *
     * @param int $userId
     * @param string $credentialIdEncoded Base64url credential id.
     * @param string $publicKeyPem
     * @param int $signCount
     * @param bool $isActive
     * @param string|null $expiresAt GMT datetime or null (never expires).
     * @return PasskeyInterface
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    private function persistPasskey(
        int $userId,
        string $credentialIdEncoded,
        string $publicKeyPem = 'pem',
        int $signCount = 0,
        bool $isActive = true,
        ?string $expiresAt = null
    ): PasskeyInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var PasskeyInterfaceFactory $passkeyFactory */
        $passkeyFactory = $objectManager->get(PasskeyInterfaceFactory::class);
        /** @var PasskeyRepositoryInterface $repository */
        $repository = $objectManager->get(PasskeyRepositoryInterface::class);

        /** @var PasskeyInterface $passkey */
        $passkey = $passkeyFactory->create();
        $passkey->setUserId($userId)
            ->setCredentialId($credentialIdEncoded)
            ->setPublicKey($publicKeyPem)
            ->setSignCount($signCount)
            ->setLabel('Test passkey')
            ->setIsActive($isActive)
            ->setExpiresAt($expiresAt);

        return $repository->save($passkey);
    }
}
