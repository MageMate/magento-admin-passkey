<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\System\Message;

use MageMate\AdminPasskey\Model\FeatureAvailability;
use Magento\Framework\Notification\MessageInterface;

/**
 * Admin notification shown when passkeys are enabled but auto-disabled because
 * Adobe IMS is the active admin authentication method (decision D6).
 *
 * Adobe IMS owns the admin login flow, so the module's passkey login,
 * registration, force-setup and password-blocking features cannot apply. The
 * notice tells administrators why passkey behaviour is inactive despite the
 * feature being switched on.
 */
class AdobeImsConflict implements MessageInterface
{
    /**
     * @var FeatureAvailability
     */
    private FeatureAvailability $featureAvailability;

    /**
     * @param FeatureAvailability $featureAvailability
     */
    public function __construct(FeatureAvailability $featureAvailability)
    {
        $this->featureAvailability = $featureAvailability;
    }

    /**
     * @inheritDoc
     */
    public function getIdentity()
    {
        return 'magemate_admin_passkey_adobe_ims_conflict';
    }

    /**
     * @inheritDoc
     */
    public function isDisplayed()
    {
        return $this->featureAvailability->isSuppressedByAdobeIms();
    }

    /**
     * @inheritDoc
     */
    public function getText()
    {
        return (string)__(
            'Admin passkeys are enabled but inactive because Adobe IMS is the active '
            . 'admin authentication method. Passkey login, registration and password '
            . 'policies are automatically disabled while Adobe IMS owns admin sign-in.'
        );
    }

    /**
     * @inheritDoc
     */
    public function getSeverity()
    {
        return self::SEVERITY_MINOR;
    }
}
