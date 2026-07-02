<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration\Model\Login;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Login\AssertionAuthenticator;
use MageMate\AdminPasskey\Model\Webauthn\RelyingPartyInterface;
use MageMate\AdminPasskey\Test\Integration\PasskeyFixtureTrait;
use MageMate\AdminPasskey\Test\Integration\WebauthnCeremonyTrait;
use Magento\TestFramework\Helper\Bootstrap;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the passwordless assertion (AC "Assertion verify
 * resolves the right user and rejects tampered/expired/replayed assertions").
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea adminhtml
 */
class AssertionAuthenticatorTest extends TestCase
{
    use WebauthnCeremonyTrait;
    use PasskeyFixtureTrait;

    /**
     * @var AssertionAuthenticator
     */
    private AssertionAuthenticator $authenticator;

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
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->authenticator = $objectManager->get(AssertionAuthenticator::class);
        $this->repository = $objectManager->get(PasskeyRepositoryInterface::class);

        $relyingParty = $objectManager->get(RelyingPartyInterface::class);
        $this->rpId = $relyingParty->getId();
        $this->origin = $relyingParty->getOrigin();
    }

    /**
     * The assertion resolves the credential owner (not another admin) and the
     * clone-detection counter and last-used timestamp are advanced.
     *
     * @return void
     */
    public function testResolvesRightUserAndAdvancesSignCount(): void
    {
        // A decoy user + credential must never be resolved for the wrong key.
        $decoyKey = $this->createEcKey();
        $decoy = $this->createAdminUser('assert_decoy');
        $this->persistPasskey((int)$decoy->getId(), 'cred-decoy', $this->publicPem($decoyKey), 5);

        $ownerKey = $this->createEcKey();
        $owner = $this->createAdminUser('assert_owner');
        $credentialId = 'cred-owner';
        $this->persistPasskey((int)$owner->getId(), $credentialId, $this->publicPem($ownerKey), 5);

        [$clientData, $authData, $signature, $challenge] = $this->buildAssertion($ownerKey, 6);

        $user = $this->authenticator->authenticate(
            $credentialId,
            $this->encodeBase64Url($clientData),
            $this->encodeBase64Url($authData),
            $this->encodeBase64Url($signature),
            $challenge
        );

        self::assertSame((int)$owner->getId(), (int)$user->getId());

        $stored = $this->repository->getByCredentialId($credentialId);
        self::assertSame(6, $stored->getSignCount());
        self::assertNotNull($stored->getLastUsedAt());
    }

    /**
     * A tampered signature is rejected with the generic error.
     *
     * @return void
     */
    public function testRejectsTamperedSignature(): void
    {
        $key = $this->createEcKey();
        $user = $this->createAdminUser('assert_tamper');
        $this->persistPasskey((int)$user->getId(), 'cred-tamper', $this->publicPem($key), 5);

        [$clientData, $authData, $signature, $challenge] = $this->buildAssertion($key, 6);
        $signature[10] = $signature[10] === "\x00" ? "\x01" : "\x00";

        $this->expectException(WebauthnException::class);
        $this->authenticator->authenticate(
            'cred-tamper',
            $this->encodeBase64Url($clientData),
            $this->encodeBase64Url($authData),
            $this->encodeBase64Url($signature),
            $challenge
        );
    }

    /**
     * A replayed assertion (sign count not increasing) is rejected — clone
     * detection.
     *
     * @return void
     */
    public function testRejectsReplayedSignCount(): void
    {
        $key = $this->createEcKey();
        $user = $this->createAdminUser('assert_replay');
        $this->persistPasskey((int)$user->getId(), 'cred-replay', $this->publicPem($key), 5);

        // Sign count equal to the stored value must not authenticate.
        [$clientData, $authData, $signature, $challenge] = $this->buildAssertion($key, 5);

        $this->expectException(WebauthnException::class);
        $this->authenticator->authenticate(
            'cred-replay',
            $this->encodeBase64Url($clientData),
            $this->encodeBase64Url($authData),
            $this->encodeBase64Url($signature),
            $challenge
        );
    }

    /**
     * An expired credential is rejected before verification.
     *
     * @return void
     */
    public function testRejectsExpiredCredential(): void
    {
        $key = $this->createEcKey();
        $user = $this->createAdminUser('assert_expired');
        $this->persistPasskey(
            (int)$user->getId(),
            'cred-expired',
            $this->publicPem($key),
            5,
            true,
            gmdate('Y-m-d H:i:s', time() - 3600)
        );

        [$clientData, $authData, $signature, $challenge] = $this->buildAssertion($key, 6);

        $this->expectException(WebauthnException::class);
        $this->authenticator->authenticate(
            'cred-expired',
            $this->encodeBase64Url($clientData),
            $this->encodeBase64Url($authData),
            $this->encodeBase64Url($signature),
            $challenge
        );
    }

    /**
     * Build a valid assertion signed by the given key at the given sign count.
     *
     * @param OpenSSLAsymmetricKey $key
     * @param int $signCount
     * @return array{0: string, 1: string, 2: string, 3: string} clientData, authData, signature, challenge.
     */
    private function buildAssertion(OpenSSLAsymmetricKey $key, int $signCount): array
    {
        $challengeB64 = $this->encodeBase64Url('assertion-challenge-value');
        $clientDataJson = $this->buildClientDataJson('webauthn.get', $challengeB64, $this->origin);
        $flags = $this->flagUserPresent | $this->flagUserVerified;
        $authData = $this->buildAssertionAuthData($this->rpId, $flags, $signCount);

        $signature = $this->signEs256($authData . hash('sha256', $clientDataJson, true), $key);

        return [$clientDataJson, $authData, $signature, $challengeB64];
    }
}
