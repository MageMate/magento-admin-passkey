<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model\Login;

use MageMate\AdminPasskey\Model\Login\PasswordLoginPolicy;
use MageMate\AdminPasskey\Test\Integration\PasskeyFixtureTrait;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the disallow-password decision (AC "Force-setup
 * redirect and disallow-password behaviours" — the password-block trigger).
 *
 * @magentoDbIsolation enabled
 */
class PasswordLoginPolicyTest extends TestCase
{
    use PasskeyFixtureTrait;

    /**
     * @var PasswordLoginPolicy
     */
    private PasswordLoginPolicy $policy;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->policy = Bootstrap::getObjectManager()->get(PasswordLoginPolicy::class);
    }

    /**
     * With the feature disabled, password login is never blocked.
     *
     * @return void
     */
    public function testNotBlockedWhenFeatureDisabled(): void
    {
        $user = $this->createAdminUser('pw_off');
        $this->persistPasskey((int)$user->getId(), 'cred-pw-off');

        self::assertFalse($this->policy->isPasswordLoginBlocked($user->getUserName()));
    }

    /**
     * With disallow on and an active passkey, password login is blocked.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/disallow_password_login 1
     * @return void
     */
    public function testBlockedWhenDisallowAndUserHasActivePasskey(): void
    {
        $user = $this->createAdminUser('pw_block');
        $this->persistPasskey((int)$user->getId(), 'cred-pw-block');

        self::assertTrue($this->policy->isPasswordLoginBlocked($user->getUserName()));
    }

    /**
     * A user without a passkey keeps password login (never locked out).
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/disallow_password_login 1
     * @return void
     */
    public function testNotBlockedWhenUserHasNoPasskey(): void
    {
        $user = $this->createAdminUser('pw_nopass');

        self::assertFalse($this->policy->isPasswordLoginBlocked($user->getUserName()));
    }

    /**
     * Only an expired passkey does not block password login (no lockout).
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/disallow_password_login 1
     * @return void
     */
    public function testNotBlockedWhenOnlyExpiredPasskey(): void
    {
        $user = $this->createAdminUser('pw_expired');
        $this->persistPasskey(
            (int)$user->getId(),
            'cred-pw-expired',
            'pem',
            0,
            true,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );

        self::assertFalse($this->policy->isPasswordLoginBlocked($user->getUserName()));
    }

    /**
     * An unknown username is never blocked.
     *
     * @magentoConfigFixture adminpasskey/general/enabled 1
     * @magentoConfigFixture adminpasskey/general/disallow_password_login 1
     * @return void
     */
    public function testNotBlockedForUnknownUser(): void
    {
        self::assertFalse($this->policy->isPasswordLoginBlocked('no_such_admin_user'));
    }
}
