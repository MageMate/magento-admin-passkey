<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Console\Command;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Console\Command\RecoverPasswordLogin;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \MageMate\AdminPasskey\Console\Command\RecoverPasswordLogin
 */
class RecoverPasswordLoginTest extends TestCase
{
    /**
     * @var UserFactory&MockObject
     */
    private $userFactory;

    /**
     * @var UserResource&MockObject
     */
    private $userResource;

    /**
     * @var User&MockObject
     */
    private $user;

    /**
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var RecoverPasswordLogin
     */
    private RecoverPasswordLogin $command;

    protected function setUp(): void
    {
        $this->userFactory = $this->createMock(UserFactory::class);
        $this->userResource = $this->createMock(UserResource::class);
        $this->user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);

        $this->userFactory->method('create')->willReturn($this->user);

        $this->command = new RecoverPasswordLogin(
            $this->userFactory,
            $this->userResource,
            $this->repository
        );
    }

    public function testDeactivatesPasskeysForKnownUser(): void
    {
        $this->userResource->expects($this->once())
            ->method('load')
            ->with($this->user, 'admin', 'username');
        $this->user->method('getId')->willReturn(7);
        $this->repository->expects($this->once())
            ->method('deactivateForUser')
            ->with(7)
            ->willReturn(2);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['user' => 'admin']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deactivated 2 passkey(s) for "admin"', $tester->getDisplay());
    }

    public function testReportsWhenNoActivePasskeys(): void
    {
        $this->user->method('getId')->willReturn(7);
        $this->repository->method('deactivateForUser')->with(7)->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['user' => 'admin']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('already available', $tester->getDisplay());
    }

    public function testThrowsForUnknownUser(): void
    {
        $this->user->method('getId')->willReturn(null);
        $this->repository->expects($this->never())->method('deactivateForUser');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown admin user "ghost".');

        $tester = new CommandTester($this->command);
        $tester->execute(['user' => 'ghost']);
    }
}
