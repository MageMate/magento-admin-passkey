<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Controller\Adminhtml;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Functional coverage for the force-setup redirect (AC "Force-setup redirect and
 * disallow-password behaviours"). Dispatches real admin routes as the logged-in
 * admin and asserts the `controller_action_predispatch` observer behaviour.
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea adminhtml
 */
class ForceSetupRedirectTest extends AbstractBackendController
{
    /**
     * An admin without a passkey is redirected to registration when force-setup
     * is on.
     *
     * @magentoConfigFixture current_store adminpasskey/general/enabled 1
     * @magentoConfigFixture current_store adminpasskey/general/force_setup 1
     * @return void
     */
    public function testRedirectsAdminWithoutPasskey(): void
    {
        $this->dispatch('backend/admin/system_account/index');

        $this->assertRedirect($this->stringContains('passkey/register'));
    }

    /**
     * The registration route itself is allow-listed, so it is never redirected
     * (which would otherwise be an infinite loop).
     *
     * @magentoConfigFixture current_store adminpasskey/general/enabled 1
     * @magentoConfigFixture current_store adminpasskey/general/force_setup 1
     * @return void
     */
    public function testDoesNotRedirectRegistrationRoute(): void
    {
        $this->dispatch('backend/passkey/register/index');

        self::assertStringNotContainsString(
            'passkey/register',
            (string)$this->getResponse()->getHeader('Location')
        );
    }

    /**
     * With force-setup off, ordinary admin pages are not redirected.
     *
     * @return void
     */
    public function testDoesNotRedirectWhenForceSetupDisabled(): void
    {
        $this->dispatch('backend/admin/system_account/index');

        self::assertStringNotContainsString(
            'passkey/register',
            (string)$this->getResponse()->getHeader('Location')
        );
    }
}
