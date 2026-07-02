<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Registration;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\Data\PasskeyInterfaceFactory;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationRequest;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationResult;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use MageMate\AdminPasskey\Model\Webauthn\RegistrationVerifierInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

/**
 * Verifies a registration attestation and persists the resulting credential.
 *
 * Keeps the controller thin: the controller decodes the HTTP payload, this
 * service owns verification, duplicate rejection, and storage.
 */
class CredentialRegistrar
{
    /**
     * Fallback label when the admin does not name the passkey.
     */
    private const DEFAULT_LABEL = 'Passkey';

    /**
     * @var RegistrationVerifierInterface
     */
    private RegistrationVerifierInterface $verifier;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @var PasskeyInterfaceFactory
     */
    private PasskeyInterfaceFactory $passkeyFactory;

    /**
     * @var ExpiryResolver
     */
    private ExpiryResolver $expiryResolver;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Base64Url
     */
    private Base64Url $base64Url;

    /**
     * @var JsonSerializer
     */
    private JsonSerializer $serializer;

    /**
     * @param RegistrationVerifierInterface $verifier
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param PasskeyInterfaceFactory $passkeyFactory
     * @param ExpiryResolver $expiryResolver
     * @param Config $config
     * @param Base64Url $base64Url
     * @param JsonSerializer $serializer
     */
    public function __construct(
        RegistrationVerifierInterface $verifier,
        PasskeyRepositoryInterface $passkeyRepository,
        PasskeyInterfaceFactory $passkeyFactory,
        ExpiryResolver $expiryResolver,
        Config $config,
        Base64Url $base64Url,
        JsonSerializer $serializer
    ) {
        $this->verifier = $verifier;
        $this->passkeyRepository = $passkeyRepository;
        $this->passkeyFactory = $passkeyFactory;
        $this->expiryResolver = $expiryResolver;
        $this->config = $config;
        $this->base64Url = $base64Url;
        $this->serializer = $serializer;
    }

    /**
     * Verify the ceremony response and store the credential for the user.
     *
     * @param int $userId
     * @param string $challenge Base64url challenge issued for this ceremony.
     * @param string $clientDataJson Base64url clientDataJSON.
     * @param string $attestationObject Base64url attestation object.
     * @param string[] $transports Client-reported transports.
     * @param string $label User-supplied passkey name.
     * @return PasskeyInterface
     * @throws AlreadyExistsException When the credential is already registered.
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \MageMate\AdminPasskey\Exception\WebauthnException
     */
    public function register(
        int $userId,
        string $challenge,
        string $clientDataJson,
        string $attestationObject,
        array $transports,
        string $label
    ): PasskeyInterface {
        $result = $this->verifier->verify(new RegistrationRequest(
            $this->base64Url->decode($clientDataJson),
            $this->base64Url->decode($attestationObject),
            $challenge,
            $this->config->isUserVerificationRequired(),
            $transports
        ));

        if ($this->credentialExists($result->getCredentialIdEncoded())) {
            throw new AlreadyExistsException(new Phrase('This passkey is already registered.'));
        }

        return $this->passkeyRepository->save($this->build($userId, $result, $label));
    }

    /**
     * Build a passkey entity from a verified registration result.
     *
     * @param int $userId
     * @param RegistrationResult $result
     * @param string $label
     * @return PasskeyInterface
     */
    private function build(int $userId, RegistrationResult $result, string $label): PasskeyInterface
    {
        $transports = $result->getTransports();

        /** @var PasskeyInterface $passkey */
        $passkey = $this->passkeyFactory->create();

        return $passkey->setUserId($userId)
            ->setCredentialId($result->getCredentialIdEncoded())
            ->setPublicKey($result->getPublicKeyPem())
            ->setSignCount($result->getSignCount())
            ->setAaguid($result->getAaguid())
            ->setTransports($transports === [] ? null : $this->serializer->serialize($transports))
            ->setLabel($this->sanitizeLabel($label))
            ->setAttestationFmt($result->getAttestationFormat())
            ->setIsActive(true)
            ->setExpiresAt($this->expiryResolver->resolve());
    }

    /**
     * Whether a credential with this base64url id already exists.
     *
     * @param string $credentialIdEncoded
     * @return bool
     */
    private function credentialExists(string $credentialIdEncoded): bool
    {
        try {
            $this->passkeyRepository->getByCredentialId($credentialIdEncoded);
        } catch (NoSuchEntityException $e) {
            return false;
        }

        return true;
    }

    /**
     * Normalise a user-supplied label to a bounded, non-empty string.
     *
     * @param string $label
     * @return string
     */
    private function sanitizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return self::DEFAULT_LABEL;
        }

        return mb_substr($label, 0, 255);
    }
}
