<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\AuthenticatorData;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationRequest;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationResult;
use MageMate\AdminPasskey\Model\Webauthn\Internal\AuthenticatorDataParser;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CborDecoder;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ClientDataValidator;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CoseKeyConverter;

/**
 * Verifies a WebAuthn registration (attestation) response.
 *
 * Attestation conveyance is "none" by default (privacy — see PRD decision D5),
 * so the attestation statement itself is not cryptographically verified; the
 * ceremony binding (origin, RP id, challenge, user presence/verification) and
 * the credential public key extraction are what this class enforces.
 */
class RegistrationVerifier implements RegistrationVerifierInterface
{
    /**
     * Ceremony type for a registration response.
     */
    private const CEREMONY_TYPE = 'webauthn.create';

    /**
     * @var RelyingPartyInterface
     */
    private RelyingPartyInterface $relyingParty;

    /**
     * @var ClientDataValidator
     */
    private ClientDataValidator $clientDataValidator;

    /**
     * @var CborDecoder
     */
    private CborDecoder $cborDecoder;

    /**
     * @var AuthenticatorDataParser
     */
    private AuthenticatorDataParser $authenticatorDataParser;

    /**
     * @var CoseKeyConverter
     */
    private CoseKeyConverter $coseKeyConverter;

    /**
     * @var Base64Url
     */
    private Base64Url $base64Url;

    /**
     * @param RelyingPartyInterface $relyingParty
     * @param ClientDataValidator $clientDataValidator
     * @param CborDecoder $cborDecoder
     * @param AuthenticatorDataParser $authenticatorDataParser
     * @param CoseKeyConverter $coseKeyConverter
     * @param Base64Url $base64Url
     */
    public function __construct(
        RelyingPartyInterface $relyingParty,
        ClientDataValidator $clientDataValidator,
        CborDecoder $cborDecoder,
        AuthenticatorDataParser $authenticatorDataParser,
        CoseKeyConverter $coseKeyConverter,
        Base64Url $base64Url
    ) {
        $this->relyingParty = $relyingParty;
        $this->clientDataValidator = $clientDataValidator;
        $this->cborDecoder = $cborDecoder;
        $this->authenticatorDataParser = $authenticatorDataParser;
        $this->coseKeyConverter = $coseKeyConverter;
        $this->base64Url = $base64Url;
    }

    /**
     * @inheritDoc
     */
    public function verify(RegistrationRequest $request): RegistrationResult
    {
        $this->clientDataValidator->validate(
            $request->getClientDataJson(),
            self::CEREMONY_TYPE,
            $request->getExpectedChallenge(),
            $this->relyingParty->getOrigin()
        );

        $attestation = $this->cborDecoder->decode($request->getAttestationObject());
        if (!is_array($attestation) || !isset($attestation['authData'], $attestation['fmt'])) {
            throw new WebauthnException(__('The attestation object is malformed.'));
        }

        $authData = $this->authenticatorDataParser->parse($this->byteString($attestation['authData']));

        $this->assertAuthenticatorData($authData, $request->isUserVerificationRequired());

        if (!$authData->hasAttestedCredentialData()
            || $authData->getCoseKey() === null
            || $authData->getCredentialId() === null
        ) {
            throw new WebauthnException(__('The attested credential data is missing.'));
        }

        $publicKeyPem = $this->coseKeyConverter->toPem($authData->getCoseKey());
        $credentialId = $authData->getCredentialId();

        return new RegistrationResult(
            $credentialId,
            $this->base64Url->encode($credentialId),
            $publicKeyPem,
            $authData->getSignCount(),
            $this->normaliseAaguid($authData->getAaguid()),
            (string)$attestation['fmt'],
            $request->getTransports()
        );
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
     * Extract raw bytes from a CBOR byte-string wrapper (or plain string).
     *
     * @param mixed $value
     * @return string
     */
    private function byteString($value): string
    {
        if (is_object($value) && method_exists($value, 'get_byte_string')) {
            return (string)$value->get_byte_string();
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Normalise the AAGUID to a base64 string, treating the all-zero value as absent.
     *
     * @param string|null $aaguid
     * @return string|null
     */
    private function normaliseAaguid(?string $aaguid): ?string
    {
        if ($aaguid === null || trim($aaguid, "\x00") === '') {
            return null;
        }

        return base64_encode($aaguid);
    }
}
