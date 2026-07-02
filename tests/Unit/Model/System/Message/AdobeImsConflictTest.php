<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\System\Message;

use MageMate\AdminPasskey\Model\FeatureAvailability;
use MageMate\AdminPasskey\Model\System\Message\AdobeImsConflict;
use Magento\Framework\Notification\MessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\System\Message\AdobeImsConflict
 */
class AdobeImsConflictTest extends TestCase
{
    /**
     * @var FeatureAvailability&MockObject
     */
    private $feature;

    /**
     * @var AdobeImsConflict
     */
    private AdobeImsConflict $message;

    protected function setUp(): void
    {
        $this->feature = $this->createMock(FeatureAvailability::class);
        $this->message = new AdobeImsConflict($this->feature);
    }

    public function testDisplayedWhenSuppressedByAdobeIms(): void
    {
        $this->feature->method('isSuppressedByAdobeIms')->willReturn(true);

        $this->assertTrue($this->message->isDisplayed());
    }

    public function testNotDisplayedOtherwise(): void
    {
        $this->feature->method('isSuppressedByAdobeIms')->willReturn(false);

        $this->assertFalse($this->message->isDisplayed());
    }

    public function testStableIdentityAndMinorSeverity(): void
    {
        $this->assertSame('magemate_admin_passkey_adobe_ims_conflict', $this->message->getIdentity());
        $this->assertSame(MessageInterface::SEVERITY_MINOR, $this->message->getSeverity());
        $this->assertNotSame('', $this->message->getText());
    }
}
