<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Registration;

use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Registration\ExpiryResolver;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Registration\ExpiryResolver
 */
class ExpiryResolverTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var DateTime&MockObject
     */
    private $dateTime;

    private ExpiryResolver $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->resolver = new ExpiryResolver($this->config, $this->dateTime);
    }

    public function testZeroValidityMeansNoExpiry(): void
    {
        $this->config->method('getMaxValidityDays')->willReturn(0);
        $this->dateTime->expects($this->never())->method('gmtDate');

        $this->assertNull($this->resolver->resolve());
    }

    public function testExpiryIsNowPlusConfiguredDays(): void
    {
        $now = 1_700_000_000;
        $expected = $now + (30 * 86400);

        $this->config->method('getMaxValidityDays')->willReturn(30);
        $this->dateTime->method('gmtTimestamp')->willReturn($now);
        $this->dateTime->expects($this->once())
            ->method('gmtDate')
            ->with('Y-m-d H:i:s', $expected)
            ->willReturn('2024-12-14 22:13:20');

        $this->assertSame('2024-12-14 22:13:20', $this->resolver->resolve());
    }
}
