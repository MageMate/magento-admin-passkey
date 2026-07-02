<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model\ForceSetup;

use MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement;
use MageMate\AdminPasskey\Test\Integration\PasskeyFixtureTrait;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the force-setup decision (AC "Force-setup redirect
 * and disallow-password behaviours" — the redirect trigger).
 *
 * @magentoDbIsolation enabled
 */
class SetupRequirementTest extends TestCase
{
    use PasskeyFixtureTrait;

    /**
     * @var SetupRequirement
     */
    private SetupRequirement $setupRequirement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->setupRequirement = Bootstrap::getObjectManager()->get(SetupRequirement::class);
    }

    /**
     * With the feature disabled no admin is forced to set up a passkey.
     *
     * @return void
     */
    public function testNotRequiredWhenFeatureDisabled(): void
    {
        $user = $this->createAdminUser('force_off');

        self::assertFalse($this->setupRequirement->isRequiredFor((int)$user->getId()));
    }

    /**
     * With the feature and force-setup on, an admin without a passkey is forced.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/force_setup 1
     * @return void
     */
    public function testRequiredWhenEnabledAndNoPasskey(): void
    {
        $user = $this->createAdminUser('force_needs');

        self::assertTrue($this->setupRequirement->isRequiredFor((int)$user->getId()));
    }

    /**
     * An admin who already owns an active passkey is not forced.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/force_setup 1
     * @return void
     */
    public function testNotRequiredWhenUserHasActivePasskey(): void
    {
        $user = $this->createAdminUser('force_has');
        $this->persistPasskey((int)$user->getId(), 'cred-force-has');

        self::assertFalse($this->setupRequirement->isRequiredFor((int)$user->getId()));
    }

    /**
     * A guest (no resolved user id) is never forced.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/force_setup 1
     * @return void
     */
    public function testNotRequiredForGuest(): void
    {
        self::assertFalse($this->setupRequirement->isRequiredFor(0));
    }
}
