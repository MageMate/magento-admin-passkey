<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Input for {@see \MageMate\AdminPasskey\Model\Webauthn\RegistrationVerifierInterface}.
 *
 * Binary fields are the already-base64url-decoded raw bytes exactly as returned
 * by the authenticator; the challenge is the base64url value that was issued to
 * and persisted for this ceremony.
 */
class RegistrationRequest
{
    /**
     * @param string $clientDataJson Raw clientDataJSON bytes.
     * @param string $attestationObject Raw CBOR attestation object bytes.
     * @param string $expectedChallenge Base64url challenge issued for this ceremony.
     * @param bool $requireUserVerification Whether the UV flag must be set.
     * @param array $transports Client-reported transports (usb, ble, nfc, internal, hybrid).
     */
    public function __construct(
        private readonly string $clientDataJson,
        private readonly string $attestationObject,
        private readonly string $expectedChallenge,
        private readonly bool $requireUserVerification,
        private readonly array $transports = []
    ) {
    }

    /**
     * @return string
     */
    public function getClientDataJson(): string
    {
        return $this->clientDataJson;
    }

    /**
     * @return string
     */
    public function getAttestationObject(): string
    {
        return $this->attestationObject;
    }

    /**
     * @return string
     */
    public function getExpectedChallenge(): string
    {
        return $this->expectedChallenge;
    }

    /**
     * @return bool
     */
    public function isUserVerificationRequired(): bool
    {
        return $this->requireUserVerification;
    }

    /**
     * @return array
     */
    public function getTransports(): array
    {
        return $this->transports;
    }
}
