<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Test\Integration\PasskeyFixtureTrait;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the passkey repository (AC "Repository + config
 * reader behaviour" and "duplicates rejected").
 *
 * @magentoDbIsolation enabled
 */
class PasskeyRepositoryTest extends TestCase
{
    use PasskeyFixtureTrait;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->get(PasskeyRepositoryInterface::class);
    }

    /**
     * A saved credential is retrievable by its credential id.
     *
     * @return void
     */
    public function testSaveStoresCredential(): void
    {
        $user = $this->createAdminUser('repo_store');
        $saved = $this->persistPasskey((int)$user->getId(), 'cred-store-1', 'pem-data', 3);

        self::assertNotEmpty($saved->getPasskeyId());

        $loaded = $this->repository->getByCredentialId('cred-store-1');
        self::assertSame((int)$user->getId(), $loaded->getUserId());
        self::assertSame('pem-data', $loaded->getPublicKey());
        self::assertSame(3, $loaded->getSignCount());
        self::assertTrue($loaded->getIsActive());
    }

    /**
     * A second credential reusing the same credential id is rejected.
     *
     * @return void
     */
    public function testDuplicateCredentialIsRejected(): void
    {
        $user = $this->createAdminUser('repo_dupe');
        $this->persistPasskey((int)$user->getId(), 'cred-dupe-1');

        $this->expectException(CouldNotSaveException::class);
        $this->persistPasskey((int)$user->getId(), 'cred-dupe-1');
    }

    /**
     * getActiveByCredentialId rejects a deactivated credential.
     *
     * @return void
     */
    public function testGetActiveByCredentialIdRejectsInactive(): void
    {
        $user = $this->createAdminUser('repo_inactive');
        $this->persistPasskey((int)$user->getId(), 'cred-inactive-1', 'pem', 0, false);

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getActiveByCredentialId('cred-inactive-1');
    }

    /**
     * getActiveByCredentialId rejects an expired credential.
     *
     * @return void
     */
    public function testGetActiveByCredentialIdRejectsExpired(): void
    {
        $user = $this->createAdminUser('repo_expired');
        $this->persistPasskey(
            (int)$user->getId(),
            'cred-expired-1',
            'pem',
            0,
            true,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getActiveByCredentialId('cred-expired-1');
    }

    /**
     * getActiveByCredentialId returns an active, non-expired credential.
     *
     * @return void
     */
    public function testGetActiveByCredentialIdReturnsActiveNonExpired(): void
    {
        $user = $this->createAdminUser('repo_active');
        $this->persistPasskey(
            (int)$user->getId(),
            'cred-active-1',
            'pem',
            0,
            true,
            gmdate('Y-m-d H:i:s', time() + 86400)
        );

        $loaded = $this->repository->getActiveByCredentialId('cred-active-1');
        self::assertSame('cred-active-1', $loaded->getCredentialId());
    }

    /**
     * hasActivePasskey reflects active, non-expired ownership only.
     *
     * @return void
     */
    public function testHasActivePasskeyReflectsOwnership(): void
    {
        $withPasskey = $this->createAdminUser('repo_has');
        $this->persistPasskey((int)$withPasskey->getId(), 'cred-has-1');
        self::assertTrue($this->repository->hasActivePasskey((int)$withPasskey->getId()));

        $expiredOnly = $this->createAdminUser('repo_exponly');
        $this->persistPasskey(
            (int)$expiredOnly->getId(),
            'cred-exponly-1',
            'pem',
            0,
            true,
            gmdate('Y-m-d H:i:s', time() - 60)
        );
        self::assertFalse($this->repository->hasActivePasskey((int)$expiredOnly->getId()));

        $none = $this->createAdminUser('repo_none');
        self::assertFalse($this->repository->hasActivePasskey((int)$none->getId()));
    }

    /**
     * getListByUserId honours the active-only flag.
     *
     * @return void
     */
    public function testGetListByUserIdFiltersActive(): void
    {
        $user = $this->createAdminUser('repo_list');
        $userId = (int)$user->getId();
        $this->persistPasskey($userId, 'cred-list-active');
        $this->persistPasskey($userId, 'cred-list-inactive', 'pem', 0, false);

        self::assertCount(2, $this->repository->getListByUserId($userId));
        self::assertCount(1, $this->repository->getListByUserId($userId, true));
    }

    /**
     * deactivateForUser flips every active passkey off and returns the count.
     *
     * @return void
     */
    public function testDeactivateForUserDeactivatesActivePasskeys(): void
    {
        $user = $this->createAdminUser('repo_deact');
        $userId = (int)$user->getId();
        $this->persistPasskey($userId, 'cred-deact-1');
        $this->persistPasskey($userId, 'cred-deact-2');

        self::assertSame(2, $this->repository->deactivateForUser($userId));
        self::assertFalse($this->repository->hasActivePasskey($userId));
        self::assertCount(0, $this->repository->getListByUserId($userId, true));
    }
}
