<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results for admin passkeys.
 *
 * @api
 */
interface PasskeySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get passkey list.
     *
     * @return PasskeyInterface[]
     */
    public function getItems();

    /**
     * Set passkey list.
     *
     * @param PasskeyInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
