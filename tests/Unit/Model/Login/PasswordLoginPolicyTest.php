<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Login;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\FeatureAvailability;
use MageMate\AdminPasskey\Model\Login\PasswordLoginPolicy;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Login\PasswordLoginPolicy
 */
class PasswordLoginPolicyTest extends TestCase
{
    /**
     * @var FeatureAvailability&MockObject
     */
    private $feature;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var UserFactory&MockObject
     */
    private $userFactory;

    /**
     * @var User&MockObject
     */
    private $user;

    /**
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var PasswordLoginPolicy
     */
    private PasswordLoginPolicy $policy;

    protected function setUp(): void
    {
        $this->feature = $this->createMock(FeatureAvailability::class);
        $this->config = $this->createMock(Config::class);
        $this->userFactory = $this->createMock(UserFactory::class);
        $this->user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadByUsername', 'getId'])
            ->getMock();
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);

        $this->policy = new PasswordLoginPolicy(
            $this->feature,
            $this->config,
            $this->userFactory,
            $this->repository
        );
    }

    public function testBlockedWhenAvailableDisallowedAndUserHasActivePasskey(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isPasswordLoginDisallowed')->willReturn(true);
        $this->userFactory->method('create')->willReturn($this->user);
        $this->user->method('loadByUsername')->with('admin')->willReturnSelf();
        $this->user->method('getId')->willReturn(7);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(true);

        $this->assertTrue($this->policy->isPasswordLoginBlocked('admin'));
    }

    public function testNotBlockedWhenUserHasNoActivePasskey(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isPasswordLoginDisallowed')->willReturn(true);
        $this->userFactory->method('create')->willReturn($this->user);
        $this->user->method('loadByUsername')->willReturnSelf();
        $this->user->method('getId')->willReturn(7);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(false);

        $this->assertFalse($this->policy->isPasswordLoginBlocked('admin'));
    }

    public function testNotBlockedWhenFeatureUnavailable(): void
    {
        // Covers both the disabled config and the Adobe-IMS-active case (D6):
        // FeatureAvailability::isEnabled() folds them into one signal.
        $this->feature->method('isEnabled')->willReturn(false);
        $this->config->expects($this->never())->method('isPasswordLoginDisallowed');
        $this->userFactory->expects($this->never())->method('create');

        $this->assertFalse($this->policy->isPasswordLoginBlocked('admin'));
    }

    public function testNotBlockedWhenDisallowFlagOff(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isPasswordLoginDisallowed')->willReturn(false);
        $this->userFactory->expects($this->never())->method('create');

        $this->assertFalse($this->policy->isPasswordLoginBlocked('admin'));
    }

    public function testNotBlockedWhenUsernameEmpty(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isPasswordLoginDisallowed')->willReturn(true);
        $this->userFactory->expects($this->never())->method('create');

        $this->assertFalse($this->policy->isPasswordLoginBlocked('   '));
    }

    public function testNotBlockedWhenUsernameUnknown(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isPasswordLoginDisallowed')->willReturn(true);
        $this->userFactory->method('create')->willReturn($this->user);
        $this->user->method('loadByUsername')->willReturnSelf();
        $this->user->method('getId')->willReturn(null);
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->policy->isPasswordLoginBlocked('ghost'));
    }
}
