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
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Inline rename of passkey labels from the management grid (ACL-guarded).
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'MageMate_AdminPasskey::manage_own';

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

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
     * @param JsonFactory $jsonFactory
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param AccessValidator $accessValidator
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        PasskeyRepositoryInterface $passkeyRepository,
        AccessValidator $accessValidator
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->passkeyRepository = $passkeyRepository;
        $this->accessValidator = $accessValidator;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $request = $this->getRequest();
        $items = $request->getParam('items', []);
        $errors = [];

        if (!$request->getParam('isAjax') || !is_array($items) || $items === []) {
            return $result->setData([
                'messages' => [(string)__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        foreach ($items as $item) {
            $passkeyId = (int)($item[PasskeyInterface::PASSKEY_ID] ?? 0);
            $error = $this->updateLabel($passkeyId, (string)($item[PasskeyInterface::LABEL] ?? ''));
            if ($error !== null) {
                $errors[] = (string)__('[Passkey ID: %1] %2', $passkeyId, $error);
            }
        }

        return $result->setData([
            'messages' => $errors,
            'error' => $errors !== [],
        ]);
    }

    /**
     * Rename a single passkey, returning an error message on failure or null on success.
     *
     * @param int $passkeyId
     * @param string $label
     * @return string|null
     */
    private function updateLabel(int $passkeyId, string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return (string)__('The label cannot be empty.');
        }

        try {
            $passkey = $this->passkeyRepository->getById($passkeyId);
        } catch (NoSuchEntityException $e) {
            return (string)__('The passkey no longer exists.');
        }

        if (!$this->accessValidator->canManage($passkey)) {
            return (string)__('You are not allowed to rename this passkey.');
        }

        try {
            $passkey->setLabel($label);
            $this->passkeyRepository->save($passkey);
        } catch (\Throwable $e) {
            return (string)__('The passkey could not be saved.');
        }

        return null;
    }
}
