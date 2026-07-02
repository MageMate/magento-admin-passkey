<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Login;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Tfa\TwoFactorAuthBridge;
use MageMate\AdminPasskey\Model\Webauthn\AssertionVerifierInterface;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionRequest;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/**
 * Runs the server side of the passwordless assertion (login) ceremony.
 *
 * Resolves the credential from a discoverable-credential response, verifies the
 * assertion, updates the clone-detection counter and last-used timestamp, and —
 * on success — establishes the backend auth session and dispatches
 * backend_auth_user_login_success (the D2 approved passwordless path).
 *
 * Every failure surfaces a single generic {@see WebauthnException} so a caller
 * cannot distinguish "unknown credential" from "bad signature" (anti-enumeration).
 */
class AssertionAuthenticator
{
    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @var AssertionVerifierInterface
     */
    private AssertionVerifierInterface $verifier;

    /**
     * @var Base64Url
     */
    private Base64Url $base64Url;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * @var Session
     */
    private Session $authSession;

    /**
     * @var EventManager
     */
    private EventManager $eventManager;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var TwoFactorAuthBridge
     */
    private TwoFactorAuthBridge $twoFactorAuthBridge;

    /**
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param AssertionVerifierInterface $verifier
     * @param Base64Url $base64Url
     * @param Config $config
     * @param UserFactory $userFactory
     * @param Session $authSession
     * @param EventManager $eventManager
     * @param DateTime $dateTime
     * @param TwoFactorAuthBridge $twoFactorAuthBridge
     */
    public function __construct(
        PasskeyRepositoryInterface $passkeyRepository,
        AssertionVerifierInterface $verifier,
        Base64Url $base64Url,
        Config $config,
        UserFactory $userFactory,
        Session $authSession,
        EventManager $eventManager,
        DateTime $dateTime,
        TwoFactorAuthBridge $twoFactorAuthBridge
    ) {
        $this->passkeyRepository = $passkeyRepository;
        $this->verifier = $verifier;
        $this->base64Url = $base64Url;
        $this->config = $config;
        $this->userFactory = $userFactory;
        $this->authSession = $authSession;
        $this->eventManager = $eventManager;
        $this->dateTime = $dateTime;
        $this->twoFactorAuthBridge = $twoFactorAuthBridge;
    }

    /**
     * Verify the assertion response and log the resolved admin in.
     *
     * @param string $credentialId Base64url credential id from the response.
     * @param string $clientDataJson Base64url clientDataJSON.
     * @param string $authenticatorData Base64url authenticator data.
     * @param string $signature Base64url assertion signature.
     * @param string $challenge Base64url challenge issued for this ceremony.
     * @return User The authenticated admin user.
     * @throws WebauthnException When any step fails (single generic message).
     */
    public function authenticate(
        string $credentialId,
        string $clientDataJson,
        string $authenticatorData,
        string $signature,
        string $challenge
    ): User {
        if ($credentialId === '' || $clientDataJson === '' || $authenticatorData === '' || $signature === '') {
            throw new WebauthnException(__('The passkey could not be verified.'));
        }

        $passkey = $this->resolveCredential($credentialId);
        $user = $this->resolveUser($passkey);

        $result = $this->verifier->verify(new AssertionRequest(
            $this->base64Url->decode($clientDataJson),
            $this->base64Url->decode($authenticatorData),
            $this->base64Url->decode($signature),
            (string)$passkey->getPublicKey(),
            $passkey->getSignCount(),
            $challenge,
            $this->config->isUserVerificationRequired()
        ));

        $this->recordUsage($passkey, $result->getNewSignCount());
        $this->establishSession($user);

        return $user;
    }

    /**
     * Resolve an active, non-expired credential by its base64url id.
     *
     * @param string $credentialId
     * @return PasskeyInterface
     * @throws WebauthnException
     */
    private function resolveCredential(string $credentialId): PasskeyInterface
    {
        try {
            return $this->passkeyRepository->getActiveByCredentialId($credentialId);
        } catch (\Throwable $e) {
            throw new WebauthnException(__('The passkey could not be verified.'));
        }
    }

    /**
     * Load the credential owner and enforce that the account is active.
     *
     * @param PasskeyInterface $passkey
     * @return User
     * @throws WebauthnException
     */
    private function resolveUser(PasskeyInterface $passkey): User
    {
        /** @var User $user */
        $user = $this->userFactory->create();
        $user->load((int)$passkey->getUserId());

        if (!$user->getId() || !$user->getIsActive()) {
            throw new WebauthnException(__('The passkey could not be verified.'));
        }

        return $user;
    }

    /**
     * Persist the updated sign count and last-used timestamp.
     *
     * @param PasskeyInterface $passkey
     * @param int $newSignCount
     * @return void
     * @throws WebauthnException
     */
    private function recordUsage(PasskeyInterface $passkey, int $newSignCount): void
    {
        $passkey->setSignCount($newSignCount);
        $passkey->setLastUsedAt($this->dateTime->gmtDate());

        try {
            $this->passkeyRepository->save($passkey);
        } catch (\Throwable $e) {
            throw new WebauthnException(__('The passkey could not be verified.'));
        }
    }

    /**
     * Establish the backend auth session and dispatch the login-success event.
     *
     * Mirrors the core password login side effects without a password: the
     * session user is set, the session is regenerated via processLogin(), the
     * login is recorded, and backend_auth_user_login_success is dispatched so
     * downstream listeners (2FA, logging) run as they would for a normal login.
     *
     * After the session is established, the 2FA session is granted when
     * `satisfies_2fa` is on so the passkey stands in for the configured second
     * factor; the grant is written after processLogin() so it survives the
     * session-id regeneration.
     *
     * @param User $user
     * @return void
     */
    private function establishSession(User $user): void
    {
        $this->authSession->setUser($user);
        $this->authSession->processLogin();
        $user->getResource()->recordLogin($user);
        $this->eventManager->dispatch('backend_auth_user_login_success', ['user' => $user]);
        $this->twoFactorAuthBridge->grantIfPasskeySatisfiesTwoFactor();
    }
}
