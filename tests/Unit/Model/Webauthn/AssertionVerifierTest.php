<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Webauthn;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\AssertionVerifier;
use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionRequest;
use MageMate\AdminPasskey\Model\Webauthn\Internal\AuthenticatorDataParser;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CborDecoder;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ClientDataValidator;
use MageMate\AdminPasskey\Model\Webauthn\RelyingPartyInterface;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Webauthn\AssertionVerifier
 */
class AssertionVerifierTest extends TestCase
{
    use WebauthnFixtures;

    private const RP_ID = 'admin.example.com';
    private const ORIGIN = 'https://admin.example.com';

    private AssertionVerifier $verifier;

    private OpenSSLAsymmetricKey $key;

    private string $publicPem;

    protected function setUp(): void
    {
        $relyingParty = $this->createMock(RelyingPartyInterface::class);
        $relyingParty->method('getId')->willReturn(self::RP_ID);
        $relyingParty->method('getOrigin')->willReturn(self::ORIGIN);

        $this->verifier = new AssertionVerifier(
            $relyingParty,
            new ClientDataValidator(),
            new AuthenticatorDataParser(new CborDecoder())
        );

        $this->key = $this->createEcKey();
        $this->publicPem = $this->publicPem($this->key);
    }

    public function testVerifiesValidAssertionAndReturnsNewSignCount(): void
    {
        $request = $this->buildRequest();

        $result = $this->verifier->verify($request);

        self::assertSame(6, $result->getNewSignCount());
        self::assertTrue($result->isUserVerified());
    }

    public function testRejectsTamperedSignature(): void
    {
        $request = $this->buildRequest(signatureMutator: static function (string $signature): string {
            $signature[10] = $signature[10] === "\x00" ? "\x01" : "\x00";

            return $signature;
        });

        $this->expectException(WebauthnException::class);
        $this->verifier->verify($request);
    }

    public function testRejectsMismatchedOrigin(): void
    {
        $request = $this->buildRequest(origin: 'https://evil.example.com');

        $this->expectExceptionMessage('The origin is not allowed.');
        $this->verifier->verify($request);
    }

    public function testRejectsMismatchedChallenge(): void
    {
        $request = $this->buildRequest(expectedChallengeOverride: $this->encodeChallenge('a-different-challenge'));

        $this->expectExceptionMessage('The challenge does not match.');
        $this->verifier->verify($request);
    }

    public function testRejectsMismatchedRelyingParty(): void
    {
        $request = $this->buildRequest(rpId: 'attacker.example.com');

        $this->expectExceptionMessage('The relying party does not match.');
        $this->verifier->verify($request);
    }

    public function testRejectsMissingUserVerificationWhenRequired(): void
    {
        $request = $this->buildRequest(flags: $this->flagUserPresent, requireUserVerification: true);

        $this->expectExceptionMessage('User verification is required but was not performed.');
        $this->verifier->verify($request);
    }

    public function testAllowsMissingUserVerificationWhenNotRequired(): void
    {
        $request = $this->buildRequest(flags: $this->flagUserPresent, requireUserVerification: false);

        $result = $this->verifier->verify($request);

        self::assertFalse($result->isUserVerified());
    }

    public function testRejectsMissingUserPresence(): void
    {
        $request = $this->buildRequest(flags: 0x00, requireUserVerification: false);

        $this->expectExceptionMessage('User presence was not verified.');
        $this->verifier->verify($request);
    }

    public function testRejectsClonedAuthenticatorWhenSignCountDoesNotIncrease(): void
    {
        $request = $this->buildRequest(signCount: 5, storedSignCount: 5);

        $this->expectExceptionMessage('The credential signature counter is invalid.');
        $this->verifier->verify($request);
    }

    public function testAllowsZeroSignCountForCounterlessAuthenticators(): void
    {
        $request = $this->buildRequest(signCount: 0, storedSignCount: 0);

        $result = $this->verifier->verify($request);

        self::assertSame(0, $result->getNewSignCount());
    }

    /**
     * Assemble an assertion request, signing the crafted data so the signature is
     * valid unless a mutator deliberately breaks it.
     *
     * @param string $origin
     * @param string $rpId
     * @param int $flags
     * @param int $signCount
     * @param int $storedSignCount
     * @param bool $requireUserVerification
     * @param string|null $expectedChallengeOverride
     * @param callable|null $signatureMutator
     * @return AssertionRequest
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    private function buildRequest(
        string $origin = self::ORIGIN,
        string $rpId = self::RP_ID,
        ?int $flags = null,
        int $signCount = 6,
        int $storedSignCount = 5,
        bool $requireUserVerification = true,
        ?string $expectedChallengeOverride = null,
        ?callable $signatureMutator = null
    ): AssertionRequest {
        $flags = $flags ?? ($this->flagUserPresent | $this->flagUserVerified);
        $challengeB64 = $this->encodeChallenge('assertion-challenge-value');
        $clientDataJson = $this->buildClientDataJson('webauthn.get', $challengeB64, $origin);
        $authData = $this->buildAssertionAuthData($rpId, $flags, $signCount);

        $signedData = $authData . hash('sha256', $clientDataJson, true);
        $signature = $this->signEs256($signedData, $this->key);
        if ($signatureMutator !== null) {
            $signature = $signatureMutator($signature);
        }

        return new AssertionRequest(
            $clientDataJson,
            $authData,
            $signature,
            $this->publicPem,
            $storedSignCount,
            $expectedChallengeOverride ?? $challengeB64,
            $requireUserVerification
        );
    }
}
