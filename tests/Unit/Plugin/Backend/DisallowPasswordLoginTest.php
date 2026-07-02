<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Plugin\Backend;

use MageMate\AdminPasskey\Model\Login\PasswordLoginPolicy;
use MageMate\AdminPasskey\Plugin\Backend\DisallowPasswordLogin;
use Magento\Backend\Model\Auth;
use Magento\Framework\Exception\AuthenticationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Plugin\Backend\DisallowPasswordLogin
 */
class DisallowPasswordLoginTest extends TestCase
{
    /**
     * @var PasswordLoginPolicy&MockObject
     */
    private $policy;

    /**
     * @var Auth&MockObject
     */
    private $auth;

    /**
     * @var DisallowPasswordLogin
     */
    private DisallowPasswordLogin $plugin;

    protected function setUp(): void
    {
        $this->policy = $this->createMock(PasswordLoginPolicy::class);
        $this->auth = $this->createMock(Auth::class);
        $this->plugin = new DisallowPasswordLogin($this->policy);
    }

    public function testThrowsWhenPasswordLoginBlocked(): void
    {
        $this->policy->method('isPasswordLoginBlocked')->with('admin')->willReturn(true);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Password sign-in is disabled for this account.');

        $this->plugin->beforeLogin($this->auth, 'admin', 'secret');
    }

    public function testPassesThroughWhenNotBlocked(): void
    {
        $this->policy->method('isPasswordLoginBlocked')->with('admin')->willReturn(false);

        $this->plugin->beforeLogin($this->auth, 'admin', 'secret');

        $this->addToAssertionCount(1);
    }

    public function testCoercesNullUsername(): void
    {
        $this->policy->expects($this->once())
            ->method('isPasswordLoginBlocked')
            ->with('')
            ->willReturn(false);

        $this->plugin->beforeLogin($this->auth, null, null);

        $this->addToAssertionCount(1);
    }
}
