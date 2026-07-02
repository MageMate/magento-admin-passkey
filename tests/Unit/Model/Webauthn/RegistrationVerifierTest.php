<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationRequest;
use MageMate\AdminPasskey\Model\Webauthn\Internal\AuthenticatorDataParser;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CborDecoder;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ClientDataValidator;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CoseKeyConverter;
use MageMate\AdminPasskey\Model\Webauthn\RegistrationVerifier;
use MageMate\AdminPasskey\Model\Webauthn\RelyingPartyInterface;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Webauthn\RegistrationVerifier
 */
class RegistrationVerifierTest extends TestCase
{
    use WebauthnFixtures;

    private const RP_ID = 'admin.example.com';
    private const ORIGIN = 'https://admin.example.com';

    private RegistrationVerifier $verifier;

    private OpenSSLAsymmetricKey $key;

    private string $credentialId;

    protected function setUp(): void
    {
        $relyingParty = $this->createMock(RelyingPartyInterface::class);
        $relyingParty->method('getId')->willReturn(self::RP_ID);
        $relyingParty->method('getOrigin')->willReturn(self::ORIGIN);

        $this->verifier = new RegistrationVerifier(
            $relyingParty,
            new ClientDataValidator(),
            new CborDecoder(),
            new AuthenticatorDataParser(new CborDecoder()),
            new CoseKeyConverter(),
            new Base64Url()
        );

        $this->key = $this->createEcKey();
        $this->credentialId = random_bytes(20);
    }

    public function testExtractsCredentialFromValidAttestation(): void
    {
        $request = $this->buildRequest(signCount: 1, aaguid: str_repeat("\x11", 16));

        $result = $this->verifier->verify($request);

        self::assertSame($this->credentialId, $result->getCredentialId());
        self::assertSame((new Base64Url())->encode($this->credentialId), $result->getCredentialIdEncoded());
        self::assertSame(1, $result->getSignCount());
        self::assertSame('none', $result->getAttestationFormat());
        self::assertNotNull($result->getAaguid());

        // The extracted public key must verify a signature made by the private key.
        $message = 'proof-of-possession';
        $signature = $this->signEs256($message, $this->key);
        self::assertSame(1, openssl_verify($message, $signature, $result->getPublicKeyPem(), OPENSSL_ALGO_SHA256));
    }

    public function testTreatsZeroAaguidAsAbsent(): void
    {
        $request = $this->buildRequest(aaguid: str_repeat("\x00", 16));

        $result = $this->verifier->verify($request);

        self::assertNull($result->getAaguid());
    }

    public function testRejectsMismatchedRelyingParty(): void
    {
        $request = $this->buildRequest(rpId: 'attacker.example.com');

        $this->expectExceptionMessage('The relying party does not match.');
        $this->verifier->verify($request);
    }

    public function testRejectsMissingUserVerificationWhenRequired(): void
    {
        $request = $this->buildRequest(flags: $this->flagUserPresent | $this->flagAttested);

        $this->expectExceptionMessage('User verification is required but was not performed.');
        $this->verifier->verify($request);
    }

    public function testRejectsMismatchedChallenge(): void
    {
        $request = $this->buildRequest(expectedChallengeOverride: $this->encodeChallenge('other'));

        $this->expectExceptionMessage('The challenge does not match.');
        $this->verifier->verify($request);
    }

    public function testRejectsMismatchedOrigin(): void
    {
        $request = $this->buildRequest(origin: 'https://phish.example.com');

        $this->expectExceptionMessage('The origin is not allowed.');
        $this->verifier->verify($request);
    }

    /**
     * Assemble a registration request with a crafted attestation object.
     *
     * @param string $origin
     * @param string $rpId
     * @param int|null $flags
     * @param int $signCount
     * @param string $aaguid
     * @param bool $requireUserVerification
     * @param string|null $expectedChallengeOverride
     * @return RegistrationRequest
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    private function buildRequest(
        string $origin = self::ORIGIN,
        string $rpId = self::RP_ID,
        ?int $flags = null,
        int $signCount = 0,
        string $aaguid = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00",
        bool $requireUserVerification = true,
        ?string $expectedChallengeOverride = null
    ): RegistrationRequest {
        $flags = $flags ?? ($this->flagUserPresent | $this->flagUserVerified | $this->flagAttested);
        $challengeB64 = $this->encodeChallenge('registration-challenge-value');
        $clientDataJson = $this->buildClientDataJson('webauthn.create', $challengeB64, $origin);

        $coseKey = $this->buildCoseEs256Key($this->key);
        $authData = $this->buildRegistrationAuthData(
            $rpId,
            $flags,
            $signCount,
            $this->credentialId,
            $coseKey,
            $aaguid
        );
        $attestationObject = $this->buildAttestationObject($authData);

        return new RegistrationRequest(
            $clientDataJson,
            $attestationObject,
            $expectedChallengeOverride ?? $challengeB64,
            $requireUserVerification,
            ['internal']
        );
    }
}
