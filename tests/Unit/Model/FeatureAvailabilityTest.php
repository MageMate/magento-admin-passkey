<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model;

use MageMate\AdminPasskey\Model\AdobeImsState;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\FeatureAvailability;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\FeatureAvailability
 */
class FeatureAvailabilityTest extends TestCase
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
     * @var FeatureAvailability
     */
    private FeatureAvailability $feature;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->imsState = $this->createMock(AdobeImsState::class);
        $this->feature = new FeatureAvailability($this->config, $this->imsState);
    }

    public function testEnabledWhenConfiguredOnAndImsInactive(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->imsState->method('isActive')->willReturn(false);

        $this->assertTrue($this->feature->isEnabled());
        $this->assertFalse($this->feature->isSuppressedByAdobeIms());
    }

    public function testDisabledWhenConfiguredOff(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->imsState->method('isActive')->willReturn(false);

        $this->assertFalse($this->feature->isEnabled());
        $this->assertFalse($this->feature->isSuppressedByAdobeIms());
    }

    public function testSuppressedWhenImsActiveDespiteConfiguredOn(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->imsState->method('isActive')->willReturn(true);

        $this->assertFalse($this->feature->isEnabled());
        $this->assertTrue($this->feature->isSuppressedByAdobeIms());
    }

    public function testNotSuppressedWhenImsActiveButConfiguredOff(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->imsState->method('isActive')->willReturn(true);

        $this->assertFalse($this->feature->isEnabled());
        $this->assertFalse($this->feature->isSuppressedByAdobeIms());
    }
}
