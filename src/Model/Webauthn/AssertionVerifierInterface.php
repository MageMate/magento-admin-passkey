<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionRequest;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionResult;

/**
 * Verifies an assertion (login) ceremony response against a stored credential.
 */
interface AssertionVerifierInterface
{
    /**
     * Verify the assertion response.
     *
     * @param AssertionRequest $request
     * @return AssertionResult
     * @throws WebauthnException When any verification step fails.
     */
    public function verify(AssertionRequest $request): AssertionResult;
}
