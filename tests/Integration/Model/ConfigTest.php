<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model;

use MageMate\AdminPasskey\Model\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the typed configuration reader (AC "Repository +
 * config reader behaviour"). Exercises the real system.xml/config.xml defaults
 * and store-scoped overrides via @magentoConfigFixture.
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->config = Bootstrap::getObjectManager()->get(Config::class);
    }

    /**
     * The security-first defaults ship as declared in config.xml.
     *
     * @return void
     */
    public function testSecurityFirstDefaults(): void
    {
        self::assertFalse($this->config->isEnabled());
        self::assertFalse($this->config->isForceSetup());
        self::assertFalse($this->config->isPasswordLoginDisallowed());
        self::assertSame(0, $this->config->getMaxValidityDays());
        self::assertTrue($this->config->isUserVerificationRequired());
        self::assertTrue($this->config->satisfiesTwoFactor());
        self::assertNull($this->config->getRpId());
        self::assertNull($this->config->getRpName());
    }

    /**
     * The enabled flag reflects a stored override.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @return void
     */
    public function testEnabledFlagReadsOverride(): void
    {
        self::assertTrue($this->config->isEnabled());
    }

    /**
     * The max-validity window is read as a non-negative integer.
     *
     * @magentoConfigFixture adminpasskey/general/passkey_max_validity_days 90
     * @return void
     */
    public function testMaxValidityDaysReadsOverride(): void
    {
        self::assertSame(90, $this->config->getMaxValidityDays());
    }

    /**
     * The relying party id override is trimmed and returned.
     *
     * @magentoConfigFixture adminpasskey/general/rp_id admin.example.com
     * @return void
     */
    public function testRelyingPartyIdReadsOverride(): void
    {
        self::assertSame('admin.example.com', $this->config->getRpId());
    }

    /**
     * User verification can be turned off through configuration.
     *
     * @magentoConfigFixture adminpasskey/general/require_user_verification 0
     * @return void
     */
    public function testUserVerificationCanBeDisabled(): void
    {
        self::assertFalse($this->config->isUserVerificationRequired());
    }
}
