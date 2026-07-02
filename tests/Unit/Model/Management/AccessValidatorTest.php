<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Management;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Model\Management\AccessValidator;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\AuthorizationInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Management\AccessValidator
 */
class AccessValidatorTest extends TestCase
{
    private const MANAGE_OWN = 'MageMate_AdminPasskey::manage_own';
    private const MANAGE_ALL = 'MageMate_AdminPasskey::manage_all';

    /**
     * @var AuthSession&MockObject
     */
    private $authSession;

    /**
     * @var AuthorizationInterface&MockObject
     */
    private $authorization;

    private AccessValidator $validator;

    protected function setUp(): void
    {
        // getUser is magic (Session\SessionManager::__call); declare it for mocking.
        $this->authSession = $this->getMockBuilder(AuthSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->validator = new AccessValidator($this->authSession, $this->authorization);
    }

    public function testOwnPasskeyAllowedWithManageOwn(): void
    {
        $this->mockUser(7);
        $this->authorization->method('isAllowed')
            ->willReturnMap([[self::MANAGE_OWN, null, true], [self::MANAGE_ALL, null, false]]);

        $this->assertTrue($this->validator->canManage($this->passkey(7)));
    }

    public function testOwnPasskeyDeniedWithoutManageOwn(): void
    {
        $this->mockUser(7);
        $this->authorization->method('isAllowed')
            ->willReturnMap([[self::MANAGE_OWN, null, false], [self::MANAGE_ALL, null, false]]);

        $this->assertFalse($this->validator->canManage($this->passkey(7)));
    }

    public function testOtherPasskeyRequiresManageAll(): void
    {
        $this->mockUser(7);
        $this->authorization->method('isAllowed')
            ->willReturnMap([[self::MANAGE_OWN, null, true], [self::MANAGE_ALL, null, false]]);

        $this->assertFalse($this->validator->canManage($this->passkey(99)));
    }

    public function testOtherPasskeyAllowedWithManageAll(): void
    {
        $this->mockUser(7);
        $this->authorization->method('isAllowed')
            ->willReturnMap([[self::MANAGE_OWN, null, false], [self::MANAGE_ALL, null, true]]);

        $this->assertTrue($this->validator->canManage($this->passkey(99)));
    }

    public function testNoSessionUserFallsBackToManageAll(): void
    {
        $this->authSession->method('getUser')->willReturn(null);
        $this->authorization->method('isAllowed')
            ->willReturnMap([[self::MANAGE_OWN, null, true], [self::MANAGE_ALL, null, false]]);

        $this->assertFalse($this->validator->canManage($this->passkey(7)));
    }

    /**
     * Configure the auth session to return an admin user with the given id.
     *
     * @param int $userId
     * @return void
     */
    private function mockUser(int $userId): void
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $user->method('getId')->willReturn($userId);
        $this->authSession->method('getUser')->willReturn($user);
    }

    /**
     * Build a passkey stub owned by the given user id.
     *
     * @param int $ownerId
     * @return PasskeyInterface&MockObject
     */
    private function passkey(int $ownerId): PasskeyInterface
    {
        $passkey = $this->createMock(PasskeyInterface::class);
        $passkey->method('getUserId')->willReturn($ownerId);

        return $passkey;
    }
}
