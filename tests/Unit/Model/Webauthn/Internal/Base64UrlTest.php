<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Webauthn\Internal;

use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url
 */
class Base64UrlTest extends TestCase
{
    private Base64Url $base64Url;

    protected function setUp(): void
    {
        $this->base64Url = new Base64Url();
    }

    public function testEncodesWithoutPaddingOrUnsafeCharacters(): void
    {
        // Bytes chosen so that standard base64 would contain '+', '/' and '='.
        $encoded = $this->base64Url->encode("\xff\xef\xfe\xfa");

        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
        self::assertStringNotContainsString('=', $encoded);
    }

    public function testRoundTripsArbitraryBytes(): void
    {
        $raw = random_bytes(64);

        self::assertSame($raw, $this->base64Url->decode($this->base64Url->encode($raw)));
    }
}
