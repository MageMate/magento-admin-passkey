<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Login;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Login\AssertionAuthenticator;
use MageMate\AdminPasskey\Model\Webauthn\AssertionVerifierInterface;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionResult;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Login\AssertionAuthenticator
 */
class AssertionAuthenticatorTest extends TestCase
{
    /**
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var AssertionVerifierInterface&MockObject
     */
    private $verifier;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var UserFactory&MockObject
     */
    private $userFactory;

    /**
     * @var Session&MockObject
     */
    private $authSession;

    /**
     * @var EventManager&MockObject
     */
    private $eventManager;

    /**
     * @var DateTime&MockObject
     */
    private $dateTime;

    private AssertionAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);
        $this->verifier = $this->createMock(AssertionVerifierInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->userFactory = $this->getMockBuilder(UserFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        // processLogin is a real method; setUser is magic on the session manager.
        $this->authSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['processLogin'])
            ->addMethods(['setUser'])
            ->getMock();
        $this->eventManager = $this->createMock(EventManager::class);
        $this->dateTime = $this->createMock(DateTime::class);

        $this->authenticator = new AssertionAuthenticator(
            $this->repository,
            $this->verifier,
            new Base64Url(),
            $this->config,
            $this->userFactory,
            $this->authSession,
            $this->eventManager,
            $this->dateTime
        );
    }

    public function testSuccessfulAssertionEstablishesSession(): void
    {
        $passkey = $this->createMock(PasskeyInterface::class);
        $passkey->method('getUserId')->willReturn(42);
        $passkey->method('getPublicKey')->willReturn('PEM');
        $passkey->method('getSignCount')->willReturn(5);
        $passkey->expects($this->once())->method('setSignCount')->with(6);
        $passkey->expects($this->once())->method('setLastUsedAt')->with('2026-07-02 10:00:00');

        $this->repository->method('getActiveByCredentialId')->with('cred')->willReturn($passkey);
        $this->repository->expects($this->once())->method('save')->with($passkey);

        $this->verifier->method('verify')->willReturn(new AssertionResult(6, true));
        $this->config->method('isUserVerificationRequired')->willReturn(true);
        $this->dateTime->method('gmtDate')->willReturn('2026-07-02 10:00:00');

        $resource = $this->createMock(UserResource::class);
        $resource->expects($this->once())->method('recordLogin');
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIsActive', 'getResource', 'load'])
            ->getMock();
        $user->method('getId')->willReturn(42);
        $user->method('getIsActive')->willReturn(true);
        $user->method('getResource')->willReturn($resource);
        $this->userFactory->method('create')->willReturn($user);

        $this->authSession->expects($this->once())->method('setUser')->with($user);
        $this->authSession->expects($this->once())->method('processLogin');
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('backend_auth_user_login_success', ['user' => $user]);

        $this->assertSame($user, $this->authenticator->authenticate('cred', 'AAAA', 'BBBB', 'CCCC', 'chal'));
    }

    public function testUnknownCredentialFailsGenericallyWithoutSession(): void
    {
        $this->repository->method('getActiveByCredentialId')
            ->willThrowException(new NoSuchEntityException());
        $this->authSession->expects($this->never())->method('processLogin');
        $this->eventManager->expects($this->never())->method('dispatch');

        $this->expectException(WebauthnException::class);

        $this->authenticator->authenticate('cred', 'AAAA', 'BBBB', 'CCCC', 'chal');
    }

    public function testInactiveUserIsRejected(): void
    {
        $passkey = $this->createMock(PasskeyInterface::class);
        $passkey->method('getUserId')->willReturn(42);
        $this->repository->method('getActiveByCredentialId')->willReturn($passkey);

        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIsActive', 'load'])
            ->getMock();
        $user->method('getId')->willReturn(42);
        $user->method('getIsActive')->willReturn(false);
        $this->userFactory->method('create')->willReturn($user);

        $this->verifier->expects($this->never())->method('verify');
        $this->authSession->expects($this->never())->method('processLogin');

        $this->expectException(WebauthnException::class);

        $this->authenticator->authenticate('cred', 'AAAA', 'BBBB', 'CCCC', 'chal');
    }

    public function testMissingFieldsFailBeforeLookup(): void
    {
        $this->repository->expects($this->never())->method('getActiveByCredentialId');

        $this->expectException(WebauthnException::class);

        $this->authenticator->authenticate('', 'AAAA', 'BBBB', 'CCCC', 'chal');
    }
}
