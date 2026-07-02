<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Manage;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Management\AccessValidator;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Deletes selected passkeys from the grid (ACL-guarded, own vs all per row).
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'MageMate_AdminPasskey::manage_own';

    /**
     * @var Filter
     */
    private Filter $filter;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @var AccessValidator
     */
    private AccessValidator $accessValidator;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param AccessValidator $accessValidator
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        PasskeyRepositoryInterface $passkeyRepository,
        AccessValidator $accessValidator
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->passkeyRepository = $passkeyRepository;
        $this->accessValidator = $accessValidator;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $result */
        $result = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage((string)__('Could not resolve the selected passkeys.'));
            return $result->setPath('*/*/');
        }

        $deleted = 0;
        $denied = 0;
        foreach ($collection->getItems() as $passkey) {
            /** @var PasskeyInterface $passkey */
            if (!$this->accessValidator->canManage($passkey)) {
                $denied++;
                continue;
            }
            try {
                $this->passkeyRepository->delete($passkey);
                $deleted++;
            } catch (\Throwable $e) {
                $denied++;
            }
        }

        if ($deleted > 0) {
            $this->messageManager->addSuccessMessage(
                (string)__('%1 passkey(s) have been deleted.', $deleted)
            );
        }
        if ($denied > 0) {
            $this->messageManager->addErrorMessage(
                (string)__('%1 passkey(s) could not be deleted or were not permitted.', $denied)
            );
        }

        return $result->setPath('*/*/');
    }
}
