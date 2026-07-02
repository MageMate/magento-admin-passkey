<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Controller\Adminhtml\Login;

use MageMate\AdminPasskey\Model\FeatureAvailability;
use MageMate\AdminPasskey\Model\Login\LoginChallengeStorage;
use MageMate\AdminPasskey\Model\Login\RateLimiter;
use MageMate\AdminPasskey\Model\Webauthn\AssertionOptionsFactoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Issues discoverable-credential assertion options for passwordless admin login.
 *
 * Reachable without an authenticated session by design — it does not extend the
 * backend action base, so the admin authentication plugin never runs on it.
 * The response is user-agnostic (no username input, no allowed-credential list),
 * so it leaks nothing about which accounts exist, and is rate limited per client.
 */
class Options implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var FeatureAvailability
     */
    private FeatureAvailability $featureAvailability;

    /**
     * @var AssertionOptionsFactoryInterface
     */
    private AssertionOptionsFactoryInterface $optionsFactory;

    /**
     * @var LoginChallengeStorage
     */
    private LoginChallengeStorage $challengeStorage;

    /**
     * @var RateLimiter
     */
    private RateLimiter $rateLimiter;

    /**
     * @param JsonFactory $jsonFactory
     * @param FeatureAvailability $featureAvailability
     * @param AssertionOptionsFactoryInterface $optionsFactory
     * @param LoginChallengeStorage $challengeStorage
     * @param RateLimiter $rateLimiter
     */
    public function __construct(
        JsonFactory $jsonFactory,
        FeatureAvailability $featureAvailability,
        AssertionOptionsFactoryInterface $optionsFactory,
        LoginChallengeStorage $challengeStorage,
        RateLimiter $rateLimiter
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->featureAvailability = $featureAvailability;
        $this->optionsFactory = $optionsFactory;
        $this->challengeStorage = $challengeStorage;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();

        if (!$this->featureAvailability->isEnabled() || !$this->rateLimiter->allow()) {
            return $this->error($result);
        }

        try {
            $options = $this->optionsFactory->create();
        } catch (\Throwable $e) {
            return $this->error($result);
        }

        $this->challengeStorage->store($options->getChallenge());

        return $result->setData([
            'success' => true,
            'publicKey' => $options->toArray(),
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
            'message' => (string)__('Passwordless login is not available right now.'),
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
     * the session, so form-key CSRF validation is not required here.
     *
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
