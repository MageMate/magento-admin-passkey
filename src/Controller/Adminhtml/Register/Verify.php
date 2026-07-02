<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Register;

use MageMate\AdminPasskey\Model\Registration\ChallengeStorage;
use MageMate\AdminPasskey\Model\Registration\CredentialRegistrar;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\User\Model\User;

/**
 * Verifies a registration attestation response and stores the new credential.
 */
class Verify extends Action implements HttpPostActionInterface
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
     * @var Session
     */
    private Session $authSession;

    /**
     * @var ChallengeStorage
     */
    private ChallengeStorage $challengeStorage;

    /**
     * @var CredentialRegistrar
     */
    private CredentialRegistrar $registrar;

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param Session $authSession
     * @param ChallengeStorage $challengeStorage
     * @param CredentialRegistrar $registrar
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Session $authSession,
        ChallengeStorage $challengeStorage,
        CredentialRegistrar $registrar
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->authSession = $authSession;
        $this->challengeStorage = $challengeStorage;
        $this->registrar = $registrar;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();

        /** @var User|null $user */
        $user = $this->authSession->getUser();
        if ($user === null || !$user->getId()) {
            return $this->error($result, (string)__('Your session has expired.'));
        }

        $userId = (int)$user->getId();
        $challenge = $this->challengeStorage->get($userId);
        // Single-use: consume the challenge regardless of the ceremony outcome.
        $this->challengeStorage->clear();
        if ($challenge === null) {
            return $this->error($result, (string)__('The registration challenge has expired. Please try again.'));
        }

        $request = $this->getRequest();
        $clientDataJson = (string)$request->getParam('clientDataJSON');
        $attestationObject = (string)$request->getParam('attestationObject');
        if ($clientDataJson === '' || $attestationObject === '') {
            return $this->error($result, (string)__('The registration response was incomplete.'));
        }

        try {
            $this->registrar->register(
                $userId,
                $challenge,
                $clientDataJson,
                $attestationObject,
                $this->transports($request->getParam('transports')),
                (string)$request->getParam('label')
            );
        } catch (AlreadyExistsException $e) {
            return $this->error($result, (string)__('This passkey is already registered.'));
        } catch (\Throwable $e) {
            return $this->error($result, (string)__('The passkey could not be verified or saved.'));
        }

        return $result->setData([
            'success' => true,
            'message' => (string)__('Your passkey has been registered.'),
        ]);
    }

    /**
     * Coerce the client-reported transports to a list of non-empty strings.
     *
     * @param mixed $transports
     * @return string[]
     */
    private function transports($transports): array
    {
        if (!is_array($transports)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $transports), static fn ($t) => $t !== ''));
    }

    /**
     * Build a uniform failure response.
     *
     * @param Json $result
     * @param string $message
     * @return Json
     */
    private function error(Json $result, string $message): Json
    {
        return $result->setData(['success' => false, 'message' => $message]);
    }
}
