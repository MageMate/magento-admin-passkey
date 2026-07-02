<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model;

use Exception;
use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\Data\PasskeyInterfaceFactory;
use MageMate\AdminPasskey\Api\Data\PasskeySearchResultsInterface;
use MageMate\AdminPasskey\Api\Data\PasskeySearchResultsInterfaceFactory;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey as PasskeyResource;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey\Collection;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Admin passkey repository.
 *
 * Enforces credential_id uniqueness on save and the active flag / expires_at
 * window when resolving a credential for authentication.
 */
class PasskeyRepository implements PasskeyRepositoryInterface
{
    /**
     * @var PasskeyResource
     */
    private PasskeyResource $resource;

    /**
     * @var PasskeyInterfaceFactory
     */
    private PasskeyInterfaceFactory $passkeyFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var PasskeySearchResultsInterfaceFactory
     */
    private PasskeySearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param PasskeyResource $resource
     * @param PasskeyInterfaceFactory $passkeyFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PasskeySearchResultsInterfaceFactory $searchResultsFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        PasskeyResource $resource,
        PasskeyInterfaceFactory $passkeyFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        PasskeySearchResultsInterfaceFactory $searchResultsFactory,
        DateTime $dateTime
    ) {
        $this->resource = $resource;
        $this->passkeyFactory = $passkeyFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dateTime = $dateTime;
    }

    /**
     * @inheritdoc
     */
    public function save(PasskeyInterface $passkey): PasskeyInterface
    {
        $this->assertCredentialIdUnique($passkey);

        try {
            /** @var Passkey $passkey */
            $this->resource->save($passkey);
        } catch (AlreadyExistsException $e) {
            throw new CouldNotSaveException(
                new Phrase('A passkey with this credential already exists.'),
                $e
            );
        } catch (Exception $e) {
            throw new CouldNotSaveException(
                new Phrase('Could not save the passkey: %1', [$e->getMessage()]),
                $e
            );
        }

        return $passkey;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $passkeyId): PasskeyInterface
    {
        /** @var Passkey $passkey */
        $passkey = $this->passkeyFactory->create();
        $this->resource->load($passkey, $passkeyId);

        if (!$passkey->getPasskeyId()) {
            throw new NoSuchEntityException(
                new Phrase('No passkey exists with ID "%1".', [$passkeyId])
            );
        }

        return $passkey;
    }

    /**
     * @inheritdoc
     */
    public function getByCredentialId(string $credentialId): PasskeyInterface
    {
        /** @var Passkey $passkey */
        $passkey = $this->passkeyFactory->create();
        $this->resource->load($passkey, $credentialId, PasskeyInterface::CREDENTIAL_ID);

        if (!$passkey->getPasskeyId()) {
            throw new NoSuchEntityException(
                new Phrase('No passkey exists for the supplied credential.')
            );
        }

        return $passkey;
    }

    /**
     * @inheritdoc
     */
    public function getActiveByCredentialId(string $credentialId): PasskeyInterface
    {
        $passkey = $this->getByCredentialId($credentialId);

        if (!$passkey->getIsActive() || $this->isExpired($passkey)) {
            throw new NoSuchEntityException(
                new Phrase('No active passkey exists for the supplied credential.')
            );
        }

        return $passkey;
    }

    /**
     * @inheritdoc
     */
    public function getListByUserId(int $userId, bool $activeOnly = false): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PasskeyInterface::USER_ID, $userId);

        if ($activeOnly) {
            $collection->addFieldToFilter(PasskeyInterface::IS_ACTIVE, 1);
        }

        return array_values($collection->getItems());
    }

    /**
     * @inheritdoc
     */
    public function hasActivePasskey(int $userId): bool
    {
        foreach ($this->getListByUserId($userId, true) as $passkey) {
            if (!$this->isExpired($passkey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function deactivateForUser(int $userId): int
    {
        $deactivated = 0;

        foreach ($this->getListByUserId($userId, true) as $passkey) {
            $passkey->setIsActive(false);
            $this->save($passkey);
            $deactivated++;
        }

        return $deactivated;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PasskeySearchResultsInterface
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var PasskeySearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(PasskeyInterface $passkey): bool
    {
        try {
            /** @var Passkey $passkey */
            $this->resource->delete($passkey);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(
                new Phrase('Could not delete the passkey: %1', [$e->getMessage()]),
                $e
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $passkeyId): bool
    {
        return $this->delete($this->getById($passkeyId));
    }

    /**
     * Ensure no other passkey already uses this credential ID.
     *
     * @param PasskeyInterface $passkey
     * @return void
     * @throws CouldNotSaveException
     */
    private function assertCredentialIdUnique(PasskeyInterface $passkey): void
    {
        $credentialId = (string) $passkey->getCredentialId();
        if ($credentialId === '') {
            throw new CouldNotSaveException(new Phrase('Credential ID is required.'));
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PasskeyInterface::CREDENTIAL_ID, $credentialId);

        foreach ($collection->getItems() as $existing) {
            if ((int) $existing->getPasskeyId() !== (int) $passkey->getPasskeyId()) {
                throw new CouldNotSaveException(
                    new Phrase('A passkey with this credential already exists.')
                );
            }
        }
    }

    /**
     * Whether the passkey has passed its expires_at time.
     *
     * @param PasskeyInterface $passkey
     * @return bool
     */
    private function isExpired(PasskeyInterface $passkey): bool
    {
        $expiresAt = $passkey->getExpiresAt();
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        return strtotime($expiresAt) <= $this->dateTime->gmtTimestamp();
    }
}
