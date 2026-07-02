<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Verified output of a registration ceremony — the data the persistence layer
 * stores for the new credential.
 */
class RegistrationResult
{
    /**
     * @param string $credentialId Raw credential id bytes.
     * @param string $credentialIdEncoded Base64url credential id (unique index value).
     * @param string $publicKeyPem Credential public key in PEM form.
     * @param int $signCount Initial signature counter.
     * @param string|null $aaguid Base64-encoded authenticator model id, if present.
     * @param string $attestationFormat Attestation statement format (e.g. "none").
     * @param array $transports Client-reported transports.
     */
    public function __construct(
        private readonly string $credentialId,
        private readonly string $credentialIdEncoded,
        private readonly string $publicKeyPem,
        private readonly int $signCount,
        private readonly ?string $aaguid,
        private readonly string $attestationFormat,
        private readonly array $transports
    ) {
    }

    /**
     * @return string
     */
    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    /**
     * @return string
     */
    public function getCredentialIdEncoded(): string
    {
        return $this->credentialIdEncoded;
    }

    /**
     * @return string
     */
    public function getPublicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    /**
     * @return int
     */
    public function getSignCount(): int
    {
        return $this->signCount;
    }

    /**
     * @return string|null
     */
    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    /**
     * @return string
     */
    public function getAttestationFormat(): string
    {
        return $this->attestationFormat;
    }

    /**
     * @return array
     */
    public function getTransports(): array
    {
        return $this->transports;
    }
}
