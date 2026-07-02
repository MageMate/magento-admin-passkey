<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Passkey\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Derived passkey status options for the management grid.
 */
class Status implements OptionSourceInterface
{
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const INACTIVE = 'inactive';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ACTIVE, 'label' => __('Active')],
            ['value' => self::EXPIRED, 'label' => __('Expired')],
            ['value' => self::INACTIVE, 'label' => __('Inactive')],
        ];
    }
}
