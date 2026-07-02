<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\ForceSetup;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\AdobeImsState;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement
 */
class SetupRequirementTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var AdobeImsState&MockObject
     */
    private $imsState;

    /**
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var SetupRequirement
     */
    private SetupRequirement $requirement;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->imsState = $this->createMock(AdobeImsState::class);
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);
        $this->requirement = new SetupRequirement($this->config, $this->imsState, $this->repository);
    }

    public function testRequiredWhenEnabledForcedNoImsAndNoPasskey(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(true);
        $this->imsState->method('isActive')->willReturn(false);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(false);

        $this->assertTrue($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenUserAlreadyHasPasskey(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(true);
        $this->imsState->method('isActive')->willReturn(false);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(true);

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenFeatureDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->config->expects($this->never())->method('isForceSetup');
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenForceSetupOff(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(false);
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenAdobeImsActive(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(true);
        $this->imsState->method('isActive')->willReturn(true);
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenNoUser(): void
    {
        $this->config->expects($this->never())->method('isEnabled');

        $this->assertFalse($this->requirement->isRequiredFor(0));
    }
}
