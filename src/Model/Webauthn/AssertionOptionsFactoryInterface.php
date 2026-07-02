<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionOptions;

/**
 * Builds the options for a passkey assertion (login) ceremony
 * (navigator.credentials.get()).
 */
interface AssertionOptionsFactoryInterface
{
    /**
     * Create assertion options.
     *
     * @param string[] $allowCredentialIds Base64url credential ids to allow; empty enables
     *                                      discoverable-credential (passwordless) login.
     * @return AssertionOptions
     * @throws WebauthnException
     */
    public function create(array $allowCredentialIds = []): AssertionOptions;
}
