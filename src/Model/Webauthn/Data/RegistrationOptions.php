<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Immutable PublicKeyCredentialCreationOptions produced for a registration
 * ceremony. {@see toArray()} yields the structure consumed by
 * navigator.credentials.create() (with base64url-encoded binary fields).
 */
class RegistrationOptions
{
    /**
     * @param string $challenge Base64url single-use challenge.
     * @param array $rp Relying Party descriptor {id, name}.
     * @param array $user User descriptor {id, name, displayName}.
     * @param array $pubKeyCredParams Accepted credential parameters.
     * @param array $excludeCredentials Already-registered credential descriptors.
     * @param string $attestation Attestation conveyance preference.
     * @param string $userVerification User verification requirement.
     * @param int $timeout Ceremony timeout in milliseconds.
     */
    public function __construct(
        private readonly string $challenge,
        private readonly array $rp,
        private readonly array $user,
        private readonly array $pubKeyCredParams,
        private readonly array $excludeCredentials,
        private readonly string $attestation,
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
     * Serialise to the navigator.credentials.create() option shape.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'challenge' => $this->challenge,
            'rp' => $this->rp,
            'user' => $this->user,
            'pubKeyCredParams' => $this->pubKeyCredParams,
            'timeout' => $this->timeout,
            'attestation' => $this->attestation,
            'excludeCredentials' => $this->excludeCredentials,
            'authenticatorSelection' => [
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => $this->userVerification,
            ],
        ];
    }
}
