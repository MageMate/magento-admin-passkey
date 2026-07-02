<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Api;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\Data\PasskeySearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Admin passkey repository.
 *
 * @api
 */
interface PasskeyRepositoryInterface
{
    /**
     * Save passkey (enforces credential_id uniqueness).
     *
     * @param PasskeyInterface $passkey
     * @return PasskeyInterface
     * @throws CouldNotSaveException
     */
    public function save(PasskeyInterface $passkey): PasskeyInterface;

    /**
     * Get passkey by ID.
     *
     * @param int $passkeyId
     * @return PasskeyInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $passkeyId): PasskeyInterface;

    /**
     * Get passkey by credential ID.
     *
     * @param string $credentialId
     * @return PasskeyInterface
     * @throws NoSuchEntityException
     */
    public function getByCredentialId(string $credentialId): PasskeyInterface;

    /**
     * Get an active, non-expired passkey by credential ID.
     *
     * Enforces the active flag and expires_at; throws if inactive or expired.
     *
     * @param string $credentialId
     * @return PasskeyInterface
     * @throws NoSuchEntityException
     */
    public function getActiveByCredentialId(string $credentialId): PasskeyInterface;

    /**
     * Get all passkeys for an admin user.
     *
     * @param int $userId
     * @param bool $activeOnly
     * @return PasskeyInterface[]
     */
    public function getListByUserId(int $userId, bool $activeOnly = false): array;

    /**
     * Whether the admin user owns at least one active, non-expired passkey.
     *
     * @param int $userId
     * @return bool
     */
    public function hasActivePasskey(int $userId): bool;

    /**
     * Deactivate every passkey belonging to the admin user (emergency recovery).
     *
     * Used by the recovery CLI (decision D8) to restore password login for a
     * user who is locked out — password login is only blocked while the user
     * owns an active passkey, so deactivating them re-opens password sign-in.
     *
     * @param int $userId
     * @return int Number of passkeys deactivated.
     * @throws CouldNotSaveException
     */
    public function deactivateForUser(int $userId): int;

    /**
     * Get passkeys by search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return PasskeySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PasskeySearchResultsInterface;

    /**
     * Delete passkey.
     *
     * @param PasskeyInterface $passkey
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(PasskeyInterface $passkey): bool;

    /**
     * Delete passkey by ID.
     *
     * @param int $passkeyId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $passkeyId): bool;
}
