<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Login;

use MageMate\AdminPasskey\Model\Login\AssertionAuthenticator;
use MageMate\AdminPasskey\Model\Login\LoginChallengeStorage;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Verifies a passwordless-login assertion and, on success, establishes the
 * backend session for the resolved admin.
 *
 * Like {@see Options} this action is deliberately reachable without an
 * authenticated session (it does not extend the backend action base). Every
 * failure — unknown credential, bad signature, disabled account, aborted
 * ceremony — returns the same generic error so nothing about account existence
 * or the failure cause is leaked.
 */
class Verify implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var LoginChallengeStorage
     */
    private LoginChallengeStorage $challengeStorage;

    /**
     * @var AssertionAuthenticator
     */
    private AssertionAuthenticator $authenticator;

    /**
     * @var UrlInterface
     */
    private UrlInterface $backendUrl;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param LoginChallengeStorage $challengeStorage
     * @param AssertionAuthenticator $authenticator
     * @param UrlInterface $backendUrl
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        LoginChallengeStorage $challengeStorage,
        AssertionAuthenticator $authenticator,
        UrlInterface $backendUrl
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->challengeStorage = $challengeStorage;
        $this->authenticator = $authenticator;
        $this->backendUrl = $backendUrl;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();

        $challenge = $this->challengeStorage->get();
        // Single-use: consume the challenge regardless of the ceremony outcome.
        $this->challengeStorage->clear();
        if ($challenge === null) {
            return $this->error($result);
        }

        try {
            $this->authenticator->authenticate(
                (string)$this->request->getParam('id'),
                (string)$this->request->getParam('clientDataJSON'),
                (string)$this->request->getParam('authenticatorData'),
                (string)$this->request->getParam('signature'),
                $challenge
            );
        } catch (\Throwable $e) {
            return $this->error($result);
        }

        // getStartupPageUrl() yields a menu action path (e.g. "adminhtml/dashboard");
        // it must be run through getUrl() to become a dispatchable admin URL with the
        // secret key. Sending the raw path would resolve relative in the browser to
        // .../admin/adminhtml/dashboard.
        return $result->setData([
            'success' => true,
            'redirectUrl' => $this->backendUrl->getUrl($this->backendUrl->getStartupPageUrl()),
        ]);
    }

    /**
     * Uniform generic failure response (anti-enumeration).
     *
     * @param Json $result
     * @return Json
     */
    private function error(Json $result): Json
    {
        return $result->setData([
            'success' => false,
            'message' => (string)__('Could not sign you in with a passkey. Please try again.'),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The ceremony is protected by a single-use server-issued challenge bound to
     * the session and by the assertion signature, so form-key CSRF validation is
     * not required here.
     *
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
