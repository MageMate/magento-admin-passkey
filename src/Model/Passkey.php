<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey as PasskeyResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Admin passkey entity model.
 */
class Passkey extends AbstractModel implements PasskeyInterface
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PasskeyResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getPasskeyId(): ?int
    {
        $value = $this->getData(self::PASSKEY_ID);

        return $value === null ? null : (int) $value;
    }

    /**
     * @inheritdoc
     */
    public function setPasskeyId(int $value): PasskeyInterface
    {
        return $this->setData(self::PASSKEY_ID, $value);
    }

    /**
     * @inheritdoc
     */
    public function getUserId(): ?int
    {
        $value = $this->getData(self::USER_ID);

        return $value === null ? null : (int) $value;
    }

    /**
     * @inheritdoc
     */
    public function setUserId(int $value): PasskeyInterface
    {
        return $this->setData(self::USER_ID, $value);
    }

    /**
     * @inheritdoc
     */
    public function getCredentialId(): ?string
    {
        $value = $this->getData(self::CREDENTIAL_ID);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setCredentialId(string $value): PasskeyInterface
    {
        return $this->setData(self::CREDENTIAL_ID, $value);
    }

    /**
     * @inheritdoc
     */
    public function getPublicKey(): ?string
    {
        $value = $this->getData(self::PUBLIC_KEY);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setPublicKey(string $value): PasskeyInterface
    {
        return $this->setData(self::PUBLIC_KEY, $value);
    }

    /**
     * @inheritdoc
     */
    public function getSignCount(): int
    {
        return (int) $this->getData(self::SIGN_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setSignCount(int $value): PasskeyInterface
    {
        return $this->setData(self::SIGN_COUNT, $value);
    }

    /**
     * @inheritdoc
     */
    public function getAaguid(): ?string
    {
        $value = $this->getData(self::AAGUID);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setAaguid(?string $value): PasskeyInterface
    {
        return $this->setData(self::AAGUID, $value);
    }

    /**
     * @inheritdoc
     */
    public function getTransports(): ?string
    {
        $value = $this->getData(self::TRANSPORTS);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setTransports(?string $value): PasskeyInterface
    {
        return $this->setData(self::TRANSPORTS, $value);
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): ?string
    {
        $value = $this->getData(self::LABEL);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setLabel(string $value): PasskeyInterface
    {
        return $this->setData(self::LABEL, $value);
    }

    /**
     * @inheritdoc
     */
    public function getAttestationFmt(): ?string
    {
        $value = $this->getData(self::ATTESTATION_FMT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setAttestationFmt(?string $value): PasskeyInterface
    {
        return $this->setData(self::ATTESTATION_FMT, $value);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $value): PasskeyInterface
    {
        return $this->setData(self::IS_ACTIVE, $value ? 1 : 0);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(string $value): PasskeyInterface
    {
        return $this->setData(self::CREATED_AT, $value);
    }

    /**
     * @inheritdoc
     */
    public function getLastUsedAt(): ?string
    {
        $value = $this->getData(self::LAST_USED_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setLastUsedAt(?string $value): PasskeyInterface
    {
        return $this->setData(self::LAST_USED_AT, $value);
    }

    /**
     * @inheritdoc
     */
    public function getExpiresAt(): ?string
    {
        $value = $this->getData(self::EXPIRES_AT);

        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setExpiresAt(?string $value): PasskeyInterface
    {
        return $this->setData(self::EXPIRES_AT, $value);
    }
}
