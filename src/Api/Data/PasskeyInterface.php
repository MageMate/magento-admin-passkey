<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Api\Data;

/**
 * Admin passkey (WebAuthn credential) data service object.
 *
 * @api
 */
interface PasskeyInterface
{
    public const PASSKEY_ID = 'passkey_id';
    public const USER_ID = 'user_id';
    public const CREDENTIAL_ID = 'credential_id';
    public const PUBLIC_KEY = 'public_key';
    public const SIGN_COUNT = 'sign_count';
    public const AAGUID = 'aaguid';
    public const TRANSPORTS = 'transports';
    public const LABEL = 'label';
    public const ATTESTATION_FMT = 'attestation_fmt';
    public const IS_ACTIVE = 'is_active';
    public const CREATED_AT = 'created_at';
    public const LAST_USED_AT = 'last_used_at';
    public const EXPIRES_AT = 'expires_at';

    /**
     * Get passkey ID.
     *
     * @return int|null
     */
    public function getPasskeyId(): ?int;

    /**
     * Set passkey ID.
     *
     * @param int $value
     * @return PasskeyInterface
     */
    public function setPasskeyId(int $value): PasskeyInterface;

    /**
     * Get admin user ID.
     *
     * @return int|null
     */
    public function getUserId(): ?int;

    /**
     * Set admin user ID.
     *
     * @param int $value
     * @return PasskeyInterface
     */
    public function setUserId(int $value): PasskeyInterface;

    /**
     * Get credential ID (base64url).
     *
     * @return string|null
     */
    public function getCredentialId(): ?string;

    /**
     * Set credential ID (base64url).
     *
     * @param string $value
     * @return PasskeyInterface
     */
    public function setCredentialId(string $value): PasskeyInterface;

    /**
     * Get COSE public key.
     *
     * @return string|null
     */
    public function getPublicKey(): ?string;

    /**
     * Set COSE public key.
     *
     * @param string $value
     * @return PasskeyInterface
     */
    public function setPublicKey(string $value): PasskeyInterface;

    /**
     * Get clone-detection signature counter.
     *
     * @return int
     */
    public function getSignCount(): int;

    /**
     * Set clone-detection signature counter.
     *
     * @param int $value
     * @return PasskeyInterface
     */
    public function setSignCount(int $value): PasskeyInterface;

    /**
     * Get authenticator AAGUID.
     *
     * @return string|null
     */
    public function getAaguid(): ?string;

    /**
     * Set authenticator AAGUID.
     *
     * @param string|null $value
     * @return PasskeyInterface
     */
    public function setAaguid(?string $value): PasskeyInterface;

    /**
     * Get transports (JSON array).
     *
     * @return string|null
     */
    public function getTransports(): ?string;

    /**
     * Set transports (JSON array).
     *
     * @param string|null $value
     * @return PasskeyInterface
     */
    public function setTransports(?string $value): PasskeyInterface;

    /**
     * Get user-facing label.
     *
     * @return string|null
     */
    public function getLabel(): ?string;

    /**
     * Set user-facing label.
     *
     * @param string $value
     * @return PasskeyInterface
     */
    public function setLabel(string $value): PasskeyInterface;

    /**
     * Get attestation format.
     *
     * @return string|null
     */
    public function getAttestationFmt(): ?string;

    /**
     * Set attestation format.
     *
     * @param string|null $value
     * @return PasskeyInterface
     */
    public function setAttestationFmt(?string $value): PasskeyInterface;

    /**
     * Get active flag.
     *
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Set active flag.
     *
     * @param bool $value
     * @return PasskeyInterface
     */
    public function setIsActive(bool $value): PasskeyInterface;

    /**
     * Get creation time.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set creation time.
     *
     * @param string $value
     * @return PasskeyInterface
     */
    public function setCreatedAt(string $value): PasskeyInterface;

    /**
     * Get last used time.
     *
     * @return string|null
     */
    public function getLastUsedAt(): ?string;

    /**
     * Set last used time.
     *
     * @param string|null $value
     * @return PasskeyInterface
     */
    public function setLastUsedAt(?string $value): PasskeyInterface;

    /**
     * Get expiry time.
     *
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * Set expiry time.
     *
     * @param string|null $value
     * @return PasskeyInterface
     */
    public function setExpiresAt(?string $value): PasskeyInterface;
}
