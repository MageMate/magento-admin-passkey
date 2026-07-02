<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model\Registration;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Registration\CredentialRegistrar;
use MageMate\AdminPasskey\Model\Webauthn\RelyingPartyInterface;
use MageMate\AdminPasskey\Test\Integration\PasskeyFixtureTrait;
use MageMate\AdminPasskey\Test\Integration\WebauthnCeremonyTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\TestFramework\Helper\Bootstrap;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for registration verify + persist (AC "Registration
 * verify stores a credential; duplicates rejected"). Drives the real WebAuthn
 * verifier, relying-party derivation and repository against genuine crypto.
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea adminhtml
 */
class CredentialRegistrarTest extends TestCase
{
    use WebauthnCeremonyTrait;
    use PasskeyFixtureTrait;

    /**
     * @var CredentialRegistrar
     */
    private CredentialRegistrar $registrar;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $repository;

    /**
     * @var string
     */
    private string $rpId;

    /**
     * @var string
     */
    private string $origin;

    /**
     * @var OpenSSLAsymmetricKey
     */
    private OpenSSLAsymmetricKey $key;

    /**
     * @var string
     */
    private string $credentialId;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->registrar = $objectManager->get(CredentialRegistrar::class);
        $this->repository = $objectManager->get(PasskeyRepositoryInterface::class);

        $relyingParty = $objectManager->get(RelyingPartyInterface::class);
        $this->rpId = $relyingParty->getId();
        $this->origin = $relyingParty->getOrigin();

        $this->key = $this->createEcKey();
        $this->credentialId = random_bytes(20);
    }

    /**
     * A valid attestation is verified and stored as an active credential.
     *
     * @return void
     */
    public function testRegisterStoresCredential(): void
    {
        $user = $this->createAdminUser('reg_store');
        [$challenge, $clientDataJson, $attestationObject] = $this->buildCeremony();

        $passkey = $this->registrar->register(
            (int)$user->getId(),
            $challenge,
            $this->encodeBase64Url($clientDataJson),
            $this->encodeBase64Url($attestationObject),
            ['internal'],
            'My laptop'
        );

        self::assertSame((int)$user->getId(), $passkey->getUserId());
        self::assertSame($this->encodeBase64Url($this->credentialId), $passkey->getCredentialId());
        self::assertTrue($passkey->getIsActive());
        self::assertSame('My laptop', $passkey->getLabel());

        // The stored public key must verify a signature made by the private key.
        $message = 'proof-of-possession';
        $signature = $this->signEs256($message, $this->key);
        self::assertSame(
            1,
            openssl_verify($message, $signature, (string)$passkey->getPublicKey(), OPENSSL_ALGO_SHA256)
        );

        // And it is resolvable through the repository as an active credential.
        $resolved = $this->repository->getActiveByCredentialId($passkey->getCredentialId());
        self::assertSame($passkey->getPasskeyId(), $resolved->getPasskeyId());
    }

    /**
     * Registering the same credential a second time is rejected.
     *
     * @return void
     */
    public function testDuplicateRegistrationIsRejected(): void
    {
        $user = $this->createAdminUser('reg_dupe');
        [$challenge, $clientDataJson, $attestationObject] = $this->buildCeremony();

        $this->registrar->register(
            (int)$user->getId(),
            $challenge,
            $this->encodeBase64Url($clientDataJson),
            $this->encodeBase64Url($attestationObject),
            ['internal'],
            'First'
        );

        $this->expectException(AlreadyExistsException::class);
        $this->registrar->register(
            (int)$user->getId(),
            $challenge,
            $this->encodeBase64Url($clientDataJson),
            $this->encodeBase64Url($attestationObject),
            ['internal'],
            'Second'
        );
    }

    /**
     * Assemble a valid registration ceremony bound to the derived relying party.
     *
     * @return array{0: string, 1: string, 2: string} Challenge, clientDataJSON, attestationObject.
     */
    private function buildCeremony(): array
    {
        $challengeB64 = $this->encodeBase64Url('registration-challenge-value');
        $clientDataJson = $this->buildClientDataJson('webauthn.create', $challengeB64, $this->origin);

        $flags = $this->flagUserPresent | $this->flagUserVerified | $this->flagAttested;
        $authData = $this->buildRegistrationAuthData(
            $this->rpId,
            $flags,
            1,
            $this->credentialId,
            $this->buildCoseEs256Key($this->key),
            str_repeat("\x11", 16)
        );
        $attestationObject = $this->buildAttestationObject($authData);

        return [$challengeB64, $clientDataJson, $attestationObject];
    }
}
