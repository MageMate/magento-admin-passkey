<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Input for {@see \MageMate\AdminPasskey\Model\Webauthn\AssertionVerifierInterface}.
 *
 * Binary fields are the already-base64url-decoded raw bytes from the
 * authenticator. The public key is the PEM stored at registration time and the
 * sign count is the last value persisted for the resolved credential.
 */
class AssertionRequest
{
    /**
     * @param string $clientDataJson Raw clientDataJSON bytes.
     * @param string $authenticatorData Raw authenticator data bytes.
     * @param string $signature Raw assertion signature bytes.
     * @param string $publicKeyPem Stored credential public key (PEM).
     * @param int $storedSignCount Last persisted signature counter.
     * @param string $expectedChallenge Base64url challenge issued for this ceremony.
     * @param bool $requireUserVerification Whether the UV flag must be set.
     */
    public function __construct(
        private readonly string $clientDataJson,
        private readonly string $authenticatorData,
        private readonly string $signature,
        private readonly string $publicKeyPem,
        private readonly int $storedSignCount,
        private readonly string $expectedChallenge,
        private readonly bool $requireUserVerification
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
    public function getAuthenticatorData(): string
    {
        return $this->authenticatorData;
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
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
    public function getStoredSignCount(): int
    {
        return $this->storedSignCount;
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
}
