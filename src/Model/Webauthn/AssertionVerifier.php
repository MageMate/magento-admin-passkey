<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionRequest;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionResult;
use MageMate\AdminPasskey\Model\Webauthn\Data\AuthenticatorData;
use MageMate\AdminPasskey\Model\Webauthn\Internal\AuthenticatorDataParser;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ClientDataValidator;

/**
 * Verifies a WebAuthn assertion (login) response against a stored credential.
 *
 * Enforces, in order: ceremony binding (origin, RP id, challenge), user
 * presence/verification, the assertion signature over
 * (authenticatorData || SHA-256(clientDataJSON)), and monotonic growth of the
 * signature counter to detect cloned authenticators.
 */
class AssertionVerifier implements AssertionVerifierInterface
{
    /**
     * Ceremony type for an assertion response.
     */
    private const CEREMONY_TYPE = 'webauthn.get';

    /**
     * @var RelyingPartyInterface
     */
    private RelyingPartyInterface $relyingParty;

    /**
     * @var ClientDataValidator
     */
    private ClientDataValidator $clientDataValidator;

    /**
     * @var AuthenticatorDataParser
     */
    private AuthenticatorDataParser $authenticatorDataParser;

    /**
     * @param RelyingPartyInterface $relyingParty
     * @param ClientDataValidator $clientDataValidator
     * @param AuthenticatorDataParser $authenticatorDataParser
     */
    public function __construct(
        RelyingPartyInterface $relyingParty,
        ClientDataValidator $clientDataValidator,
        AuthenticatorDataParser $authenticatorDataParser
    ) {
        $this->relyingParty = $relyingParty;
        $this->clientDataValidator = $clientDataValidator;
        $this->authenticatorDataParser = $authenticatorDataParser;
    }

    /**
     * @inheritDoc
     */
    public function verify(AssertionRequest $request): AssertionResult
    {
        $this->clientDataValidator->validate(
            $request->getClientDataJson(),
            self::CEREMONY_TYPE,
            $request->getExpectedChallenge(),
            $this->relyingParty->getOrigin()
        );

        $authData = $this->authenticatorDataParser->parse($request->getAuthenticatorData());

        $this->assertAuthenticatorData($authData, $request->isUserVerificationRequired());
        $this->assertSignature($request);
        $this->assertSignCount($request->getStoredSignCount(), $authData->getSignCount());

        return new AssertionResult($authData->getSignCount(), $authData->isUserVerified());
    }

    /**
     * Verify RP id hash and the required authenticator flags.
     *
     * @param AuthenticatorData $authData
     * @param bool $requireUserVerification
     * @return void
     * @throws WebauthnException
     */
    private function assertAuthenticatorData(AuthenticatorData $authData, bool $requireUserVerification): void
    {
        $expectedRpIdHash = hash('sha256', $this->relyingParty->getId(), true);
        if (!hash_equals($expectedRpIdHash, $authData->getRpIdHash())) {
            throw new WebauthnException(__('The relying party does not match.'));
        }

        if (!$authData->isUserPresent()) {
            throw new WebauthnException(__('User presence was not verified.'));
        }

        if ($requireUserVerification && !$authData->isUserVerified()) {
            throw new WebauthnException(__('User verification is required but was not performed.'));
        }
    }

    /**
     * Verify the assertion signature over authenticatorData || SHA-256(clientDataJSON).
     *
     * @param AssertionRequest $request
     * @return void
     * @throws WebauthnException
     */
    private function assertSignature(AssertionRequest $request): void
    {
        $signedData = $request->getAuthenticatorData()
            . hash('sha256', $request->getClientDataJson(), true);

        $publicKey = openssl_pkey_get_public($request->getPublicKeyPem());
        if ($publicKey === false) {
            throw new WebauthnException(__('The stored credential key is invalid.'));
        }

        $result = openssl_verify(
            $signedData,
            $request->getSignature(),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($result !== 1) {
            throw new WebauthnException(__('The assertion signature is invalid.'));
        }
    }

    /**
     * Enforce monotonic growth of the signature counter (clone detection).
     *
     * When both the stored and reported counters are zero the authenticator does
     * not support counters and the check is skipped; otherwise the new value must
     * be strictly greater than the stored one.
     *
     * @param int $storedSignCount
     * @param int $newSignCount
     * @return void
     * @throws WebauthnException
     */
    private function assertSignCount(int $storedSignCount, int $newSignCount): void
    {
        if ($storedSignCount === 0 && $newSignCount === 0) {
            return;
        }

        if ($newSignCount <= $storedSignCount) {
            throw new WebauthnException(__('The credential signature counter is invalid.'));
        }
    }
}
