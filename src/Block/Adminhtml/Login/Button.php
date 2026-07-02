<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Block\Adminhtml\Login;

use MageMate\AdminPasskey\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Renders the "Sign in with a passkey" button on the admin login form.
 *
 * The button is only emitted when admin passkey support is enabled, so a store
 * that has not opted in shows the stock password-only login.
 */
class Button extends Template
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(Context $context, Config $config, array $data = [])
    {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * Whether the passkey login button should be shown.
     *
     * @return bool
     */
    public function isPasskeyLoginEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * URL of the assertion-options endpoint.
     *
     * @return string
     */
    public function getOptionsUrl(): string
    {
        return $this->getUrl('passkey/login/options');
    }

    /**
     * URL of the assertion-verify endpoint.
     *
     * @return string
     */
    public function getVerifyUrl(): string
    {
        return $this->getUrl('passkey/login/verify');
    }
}
