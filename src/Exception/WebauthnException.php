<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Thrown when a WebAuthn ceremony (registration or assertion) fails verification.
 *
 * Messages are deliberately generic at the boundary to avoid credential/user
 * enumeration; the specific reason is carried for internal logging only.
 */
class WebauthnException extends LocalizedException
{
}
