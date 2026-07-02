<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\ResourceModel\Passkey;

use MageMate\AdminPasskey\Model\Passkey as PasskeyModel;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey as PasskeyResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Admin passkey collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PasskeyModel::class, PasskeyResource::class);
    }
}
