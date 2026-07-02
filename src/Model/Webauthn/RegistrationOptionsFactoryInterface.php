<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationOptions;

/**
 * Builds the options for a passkey registration ceremony
 * (navigator.credentials.create()).
 */
interface RegistrationOptionsFactoryInterface
{
    /**
     * Create registration options for the given admin user.
     *
     * @param int $userId Admin user id.
     * @param string $userName Admin user name (login).
     * @param string $displayName Human-readable display name.
     * @param string[] $excludeCredentialIds Base64url ids of the user's existing credentials.
     * @return RegistrationOptions
     * @throws WebauthnException
     */
    public function create(
        int $userId,
        string $userName,
        string $displayName,
        array $excludeCredentialIds = []
    ): RegistrationOptions;
}
