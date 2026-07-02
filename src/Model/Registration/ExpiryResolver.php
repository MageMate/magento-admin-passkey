<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Registration;

use MageMate\AdminPasskey\Model\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Derives a passkey's expires_at from the configured max-validity window.
 */
class ExpiryResolver
{
    /**
     * Seconds in one day.
     */
    private const SECONDS_PER_DAY = 86400;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Config $config
     * @param DateTime $dateTime
     */
    public function __construct(Config $config, DateTime $dateTime)
    {
        $this->config = $config;
        $this->dateTime = $dateTime;
    }

    /**
     * Resolve the GMT expiry datetime for a newly registered passkey.
     *
     * Returns null when max validity is 0 (passkeys never expire).
     *
     * @return string|null
     */
    public function resolve(): ?string
    {
        $days = $this->config->getMaxValidityDays();
        if ($days <= 0) {
            return null;
        }

        $expiresAt = $this->dateTime->gmtTimestamp() + ($days * self::SECONDS_PER_DAY);

        return $this->dateTime->gmtDate('Y-m-d H:i:s', $expiresAt);
    }
}
