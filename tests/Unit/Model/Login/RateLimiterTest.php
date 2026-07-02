<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Login;

use MageMate\AdminPasskey\Model\Login\RateLimiter;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Login\RateLimiter
 */
class RateLimiterTest extends TestCase
{
    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    /**
     * @var RemoteAddress&MockObject
     */
    private $remoteAddress;

    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->remoteAddress = $this->createMock(RemoteAddress::class);
        $this->remoteAddress->method('getRemoteAddress')->willReturn('203.0.113.7');

        $this->rateLimiter = new RateLimiter($this->cache, $this->remoteAddress);
    }

    public function testAllowsAndIncrementsUnderTheLimit(): void
    {
        $this->cache->method('load')->willReturn('3');
        $this->cache->expects($this->once())
            ->method('save')
            ->with('4', $this->isType('string'), [], 60);

        $this->assertTrue($this->rateLimiter->allow());
    }

    public function testFirstAttemptIsAllowed(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->cache->expects($this->once())->method('save')->with('1', $this->isType('string'), [], 60);

        $this->assertTrue($this->rateLimiter->allow());
    }

    public function testBlocksAtTheLimit(): void
    {
        $this->cache->method('load')->willReturn('15');
        $this->cache->expects($this->never())->method('save');

        $this->assertFalse($this->rateLimiter->allow());
    }
}
