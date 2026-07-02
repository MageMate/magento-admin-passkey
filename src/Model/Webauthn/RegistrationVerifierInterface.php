<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationRequest;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationResult;

/**
 * Verifies a registration ceremony response and extracts the credential to store.
 */
interface RegistrationVerifierInterface
{
    /**
     * Verify the attestation response and return the credential data.
     *
     * @param RegistrationRequest $request
     * @return RegistrationResult
     * @throws WebauthnException When any verification step fails.
     */
    public function verify(RegistrationRequest $request): RegistrationResult;
}
