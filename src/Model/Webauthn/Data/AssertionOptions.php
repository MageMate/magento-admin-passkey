<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Immutable PublicKeyCredentialRequestOptions produced for an assertion (login)
 * ceremony. {@see toArray()} yields the structure consumed by
 * navigator.credentials.get() (with base64url-encoded binary fields).
 */
class AssertionOptions
{
    /**
     * @param string $challenge Base64url single-use challenge.
     * @param string $rpId Relying Party id.
     * @param array $allowCredentials Allowed credential descriptors (empty = discoverable).
     * @param string $userVerification User verification requirement.
     * @param int $timeout Ceremony timeout in milliseconds.
     */
    public function __construct(
        private readonly string $challenge,
        private readonly string $rpId,
        private readonly array $allowCredentials,
        private readonly string $userVerification,
        private readonly int $timeout
    ) {
    }

    /**
     * Base64url challenge to persist server-side for verification.
     *
     * @return string
     */
    public function getChallenge(): string
    {
        return $this->challenge;
    }

    /**
     * Serialise to the navigator.credentials.get() option shape.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'challenge' => $this->challenge,
            'timeout' => $this->timeout,
            'rpId' => $this->rpId,
            'allowCredentials' => $this->allowCredentials,
            'userVerification' => $this->userVerification,
        ];
    }
}
