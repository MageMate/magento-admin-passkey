<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Immutable representation of parsed WebAuthn authenticator data.
 *
 * @see https://www.w3.org/TR/webauthn-2/#sctn-authenticator-data
 */
class AuthenticatorData
{
    /**
     * User Present flag (bit 0).
     */
    private const FLAG_UP = 0x01;

    /**
     * User Verified flag (bit 2).
     */
    private const FLAG_UV = 0x04;

    /**
     * Attested credential data included flag (bit 6).
     */
    private const FLAG_AT = 0x40;

    /**
     * @param string $rpIdHash SHA-256 hash of the RP id (32 raw bytes).
     * @param int $flags Authenticator data flags byte.
     * @param int $signCount Signature counter.
     * @param string|null $aaguid Authenticator model id (16 raw bytes) when attested.
     * @param string|null $credentialId Raw credential id when attested.
     * @param array|null $coseKey Decoded COSE public key map when attested.
     */
    public function __construct(
        private readonly string $rpIdHash,
        private readonly int $flags,
        private readonly int $signCount,
        private readonly ?string $aaguid = null,
        private readonly ?string $credentialId = null,
        private readonly ?array $coseKey = null
    ) {
    }

    /**
     * @return string
     */
    public function getRpIdHash(): string
    {
        return $this->rpIdHash;
    }

    /**
     * @return int
     */
    public function getSignCount(): int
    {
        return $this->signCount;
    }

    /**
     * @return bool
     */
    public function isUserPresent(): bool
    {
        return ($this->flags & self::FLAG_UP) !== 0;
    }

    /**
     * @return bool
     */
    public function isUserVerified(): bool
    {
        return ($this->flags & self::FLAG_UV) !== 0;
    }

    /**
     * @return bool
     */
    public function hasAttestedCredentialData(): bool
    {
        return ($this->flags & self::FLAG_AT) !== 0;
    }

    /**
     * @return string|null
     */
    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    /**
     * @return string|null
     */
    public function getCredentialId(): ?string
    {
        return $this->credentialId;
    }

    /**
     * @return array|null
     */
    public function getCoseKey(): ?array
    {
        return $this->coseKey;
    }
}
