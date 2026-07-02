<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

use MageMate\AdminPasskey\Api\Data\PasskeySearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Admin passkey search results.
 */
class PasskeySearchResults extends SearchResults implements PasskeySearchResultsInterface
{
}
