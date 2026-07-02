<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\ResourceModel;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Admin passkey resource model.
 */
class Passkey extends AbstractDb
{
    private const TABLE_NAME = 'magemate_admin_passkey';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, PasskeyInterface::PASSKEY_ID);
    }
}
