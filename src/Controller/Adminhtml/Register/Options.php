<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Register;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Registration\ChallengeStorage;
use MageMate\AdminPasskey\Model\Webauthn\RegistrationOptionsFactoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\User\Model\User;

/**
 * Issues WebAuthn creation options for the current admin and binds the
 * single-use challenge to the session.
 */
class Options extends Action implements HttpPostActionInterface
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
     * @var RegistrationOptionsFactoryInterface
     */
    private RegistrationOptionsFactoryInterface $optionsFactory;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @var ChallengeStorage
     */
    private ChallengeStorage $challengeStorage;

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param Session $authSession
     * @param RegistrationOptionsFactoryInterface $optionsFactory
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param ChallengeStorage $challengeStorage
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Session $authSession,
        RegistrationOptionsFactoryInterface $optionsFactory,
        PasskeyRepositoryInterface $passkeyRepository,
        ChallengeStorage $challengeStorage
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->authSession = $authSession;
        $this->optionsFactory = $optionsFactory;
        $this->passkeyRepository = $passkeyRepository;
        $this->challengeStorage = $challengeStorage;
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
            return $result->setData(['success' => false, 'message' => (string)__('Your session has expired.')]);
        }

        $userId = (int)$user->getId();

        try {
            $options = $this->optionsFactory->create(
                $userId,
                (string)$user->getUserName(),
                (string)$user->getName(),
                $this->existingCredentialIds($userId)
            );
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to start passkey registration.'),
            ]);
        }

        $this->challengeStorage->store($userId, $options->getChallenge());

        return $result->setData([
            'success' => true,
            'publicKey' => $options->toArray(),
        ]);
    }

    /**
     * Base64url ids of the admin's existing passkeys.
     *
     * Excluded from a fresh registration so an authenticator does not enrol twice.
     *
     * @param int $userId
     * @return string[]
     */
    private function existingCredentialIds(int $userId): array
    {
        $ids = [];
        foreach ($this->passkeyRepository->getListByUserId($userId) as $passkey) {
            /** @var PasskeyInterface $passkey */
            $credentialId = $passkey->getCredentialId();
            if ($credentialId !== null && $credentialId !== '') {
                $ids[] = $credentialId;
            }
        }

        return $ids;
    }
}
