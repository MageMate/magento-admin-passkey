<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use MageMate\AdminPasskey\Exception\WebauthnException;

/**
 * Validates the collected client data (clientDataJSON) common to both the
 * registration and assertion ceremonies: ceremony type, challenge and origin.
 *
 * @see https://www.w3.org/TR/webauthn-2/#dictionary-client-data
 */
class ClientDataValidator
{
    /**
     * Validate client data and return the decoded structure.
     *
     * @param string $rawClientDataJson Raw clientDataJSON bytes.
     * @param string $expectedType Expected ceremony type (webauthn.create|webauthn.get).
     * @param string $expectedChallenge Base64url challenge issued for this ceremony.
     * @param string $expectedOrigin Expected origin (scheme://host[:port]).
     * @return array Decoded client data.
     * @throws WebauthnException
     */
    public function validate(
        string $rawClientDataJson,
        string $expectedType,
        string $expectedChallenge,
        string $expectedOrigin
    ): array {
        $clientData = json_decode($rawClientDataJson, true);
        if (!is_array($clientData)) {
            throw new WebauthnException(__('The client data could not be decoded.'));
        }

        $type = (string)($clientData['type'] ?? '');
        $challenge = (string)($clientData['challenge'] ?? '');
        $origin = (string)($clientData['origin'] ?? '');

        if (!hash_equals($expectedType, $type)) {
            throw new WebauthnException(__('The ceremony type is invalid.'));
        }

        // Constant-time comparison guards against timing side channels; the
        // challenge is the sole anti-replay binding.
        if ($expectedChallenge === '' || !hash_equals($expectedChallenge, $challenge)) {
            throw new WebauthnException(__('The challenge does not match.'));
        }

        if (!hash_equals($expectedOrigin, $origin)) {
            throw new WebauthnException(__('The origin is not allowed.'));
        }

        return $clientData;
    }
}
