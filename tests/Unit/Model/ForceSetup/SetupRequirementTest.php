<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\ForceSetup;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\FeatureAvailability;
use MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement
 */
class SetupRequirementTest extends TestCase
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
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var SetupRequirement
     */
    private SetupRequirement $requirement;

    protected function setUp(): void
    {
        $this->feature = $this->createMock(FeatureAvailability::class);
        $this->config = $this->createMock(Config::class);
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);
        $this->requirement = new SetupRequirement($this->feature, $this->config, $this->repository);
    }

    public function testRequiredWhenAvailableForcedAndNoPasskey(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(true);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(false);

        $this->assertTrue($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenUserAlreadyHasPasskey(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(true);
        $this->repository->method('hasActivePasskey')->with(7)->willReturn(true);

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenFeatureUnavailable(): void
    {
        // Covers both the disabled config and the Adobe-IMS-active case (D6),
        // which FeatureAvailability::isEnabled() collapses into one signal.
        $this->feature->method('isEnabled')->willReturn(false);
        $this->config->expects($this->never())->method('isForceSetup');
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenForceSetupOff(): void
    {
        $this->feature->method('isEnabled')->willReturn(true);
        $this->config->method('isForceSetup')->willReturn(false);
        $this->repository->expects($this->never())->method('hasActivePasskey');

        $this->assertFalse($this->requirement->isRequiredFor(7));
    }

    public function testNotRequiredWhenNoUser(): void
    {
        $this->feature->expects($this->never())->method('isEnabled');

        $this->assertFalse($this->requirement->isRequiredFor(0));
    }
}
