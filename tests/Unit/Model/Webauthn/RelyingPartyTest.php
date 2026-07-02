<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Webauthn;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageMate\AdminPasskey\Model\Webauthn\RelyingParty;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Webauthn\RelyingParty
 */
class RelyingPartyTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var Store&MockObject
     */
    private $store;

    private RelyingParty $relyingParty;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->createMock(Store::class);
        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->relyingParty = new RelyingParty($this->scopeConfig, $this->storeManager);
    }

    public function testDerivesIdAndOriginFromBaseUrlWhenNotConfigured(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://admin.example.com/');
        $this->scopeConfig->method('getValue')->willReturn(null);

        self::assertSame('admin.example.com', $this->relyingParty->getId());
        self::assertSame('https://admin.example.com', $this->relyingParty->getOrigin());
    }

    public function testOriginKeepsNonDefaultPort(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://admin.example.com:8443/');
        $this->scopeConfig->method('getValue')->willReturn(null);

        self::assertSame('https://admin.example.com:8443', $this->relyingParty->getOrigin());
        self::assertSame('admin.example.com', $this->relyingParty->getId());
    }

    public function testConfiguredIdAndNameOverrideDerivedValues(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://admin.example.com/');
        $this->stubConfig([
            RelyingParty::XML_PATH_RP_ID => 'example.com',
            RelyingParty::XML_PATH_RP_NAME => 'Example Admin',
        ]);

        self::assertSame('example.com', $this->relyingParty->getId());
        self::assertSame('Example Admin', $this->relyingParty->getName());
    }

    public function testNameFallsBackToStoreNameThenHost(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://admin.example.com/');
        $this->stubConfig([
            'general/store_information/name' => 'Falcon Store',
        ]);

        self::assertSame('Falcon Store', $this->relyingParty->getName());
    }

    /**
     * Stub scope configuration reads from a path => value map (unknown paths null).
     *
     * @param array $values
     * @return void
     */
    private function stubConfig(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );
    }
}
