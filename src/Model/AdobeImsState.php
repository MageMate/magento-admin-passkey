<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Detects whether Adobe IMS is the active admin authentication method.
 *
 * Read via the config path rather than a hard dependency on
 * {@see \Magento\AdminAdobeIms\Service\ImsConfig} so the module keeps working
 * when the Adobe IMS module is absent or disabled. When IMS owns admin login,
 * passkey behaviour (force-setup, passwordless login, password blocking) is
 * suppressed per decision D6.
 */
class AdobeImsState
{
    /**
     * Config flag Adobe IMS sets when it takes over admin authentication.
     *
     * Mirrors \Magento\AdminAdobeIms\Service\ImsConfig::XML_PATH_ENABLED.
     */
    public const XML_PATH_ADMIN_IMS_ENABLED = 'adobe_ims/integration/admin_enabled';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Whether Adobe IMS is the active admin authentication method.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_ADMIN_IMS_ENABLED);
    }
}
