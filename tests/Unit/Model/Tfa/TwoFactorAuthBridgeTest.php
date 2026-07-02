<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Tfa;

use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Tfa\TwoFactorAuthBridge;
use Magento\Framework\ObjectManagerInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Tfa\TwoFactorAuthBridge
 */
class TwoFactorAuthBridgeTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var ObjectManagerInterface&MockObject
     */
    private $objectManager;

    /**
     * @var TwoFactorAuthBridge
     */
    private TwoFactorAuthBridge $bridge;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->bridge = new TwoFactorAuthBridge($this->config, $this->objectManager);
    }

    public function testGrantsTfaSessionWhenPasskeySatisfiesTwoFactor(): void
    {
        if (!interface_exists(TfaSessionInterface::class)) {
            $this->markTestSkipped('Magento_TwoFactorAuth is not installed.');
        }

        $this->config->method('satisfiesTwoFactor')->willReturn(true);

        $tfaSession = $this->createMock(TfaSessionInterface::class);
        $tfaSession->expects($this->once())->method('grantAccess');
        $this->objectManager->expects($this->once())
            ->method('get')
            ->with(TfaSessionInterface::class)
            ->willReturn($tfaSession);

        $this->bridge->grantIfPasskeySatisfiesTwoFactor();
    }

    public function testDoesNothingWhenSatisfiesTwoFactorOff(): void
    {
        $this->config->method('satisfiesTwoFactor')->willReturn(false);
        $this->objectManager->expects($this->never())->method('get');

        $this->bridge->grantIfPasskeySatisfiesTwoFactor();
    }
}
