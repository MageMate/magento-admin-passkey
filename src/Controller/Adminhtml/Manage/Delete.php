<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Manage;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Management\AccessValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Deletes a single passkey (ACL-guarded, own vs all).
 */
class Delete extends Action implements HttpPostActionInterface
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'MageMate_AdminPasskey::manage_own';

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
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param AccessValidator $accessValidator
     */
    public function __construct(
        Context $context,
        PasskeyRepositoryInterface $passkeyRepository,
        AccessValidator $accessValidator
    ) {
        parent::__construct($context);
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
        $passkeyId = (int)$this->getRequest()->getParam('passkey_id');

        if ($passkeyId <= 0) {
            $this->messageManager->addErrorMessage((string)__('No passkey was specified.'));
            return $result->setPath('*/*/');
        }

        try {
            $passkey = $this->passkeyRepository->getById($passkeyId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage((string)__('The passkey no longer exists.'));
            return $result->setPath('*/*/');
        }

        if (!$this->accessValidator->canManage($passkey)) {
            $this->messageManager->addErrorMessage((string)__('You are not allowed to delete this passkey.'));
            return $result->setPath('*/*/');
        }

        try {
            $this->passkeyRepository->delete($passkey);
            $this->messageManager->addSuccessMessage((string)__('The passkey has been deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage((string)__('The passkey could not be deleted.'));
        }

        return $result->setPath('*/*/');
    }
}
