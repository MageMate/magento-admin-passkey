<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Registration;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Api\Data\PasskeyInterfaceFactory;
use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use MageMate\AdminPasskey\Model\Config;
use MageMate\AdminPasskey\Model\Registration\CredentialRegistrar;
use MageMate\AdminPasskey\Model\Registration\ExpiryResolver;
use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationResult;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use MageMate\AdminPasskey\Model\Webauthn\RegistrationVerifierInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Registration\CredentialRegistrar
 */
class CredentialRegistrarTest extends TestCase
{
    /**
     * @var RegistrationVerifierInterface&MockObject
     */
    private $verifier;

    /**
     * @var PasskeyRepositoryInterface&MockObject
     */
    private $repository;

    /**
     * @var PasskeyInterfaceFactory&MockObject
     */
    private $passkeyFactory;

    /**
     * @var ExpiryResolver&MockObject
     */
    private $expiryResolver;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var JsonSerializer&MockObject
     */
    private $serializer;

    private CredentialRegistrar $registrar;

    protected function setUp(): void
    {
        $this->verifier = $this->createMock(RegistrationVerifierInterface::class);
        $this->repository = $this->createMock(PasskeyRepositoryInterface::class);
        $this->passkeyFactory = $this->getMockBuilder(PasskeyInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->expiryResolver = $this->createMock(ExpiryResolver::class);
        $this->config = $this->createMock(Config::class);
        $this->serializer = $this->createMock(JsonSerializer::class);

        $this->registrar = new CredentialRegistrar(
            $this->verifier,
            $this->repository,
            $this->passkeyFactory,
            $this->expiryResolver,
            $this->config,
            new Base64Url(),
            $this->serializer
        );
    }

    public function testDuplicateCredentialIsRejected(): void
    {
        $this->verifier->method('verify')->willReturn($this->verifiedResult());
        // getByCredentialId resolving a passkey means the credential already exists.
        $this->repository->method('getByCredentialId')
            ->willReturn($this->createMock(PasskeyInterface::class));
        $this->repository->expects($this->never())->method('save');

        $this->expectException(AlreadyExistsException::class);

        $this->registrar->register(1, 'chal', 'AAAA', 'BBBB', [], 'My Key');
    }

    public function testNewCredentialIsPersistedWithDefaultLabel(): void
    {
        $this->verifier->method('verify')->willReturn($this->verifiedResult());
        $this->repository->method('getByCredentialId')
            ->willThrowException(new NoSuchEntityException());
        $this->config->method('isUserVerificationRequired')->willReturn(true);
        $this->expiryResolver->method('resolve')->willReturn(null);

        $setters = [
            'setUserId',
            'setCredentialId',
            'setPublicKey',
            'setSignCount',
            'setAaguid',
            'setTransports',
            'setAttestationFmt',
            'setIsActive',
            'setExpiresAt',
        ];
        $passkey = $this->createMock(PasskeyInterface::class);
        foreach ($setters as $setter) {
            $passkey->method($setter)->willReturnSelf();
        }
        // Empty label falls back to the default.
        $passkey->expects($this->once())->method('setLabel')->with('Passkey')->willReturnSelf();

        $this->passkeyFactory->method('create')->willReturn($passkey);
        $this->repository->expects($this->once())->method('save')->with($passkey)->willReturn($passkey);

        $this->assertSame($passkey, $this->registrar->register(1, 'chal', 'AAAA', 'BBBB', [], '  '));
    }

    /**
     * A verified registration result fixture.
     *
     * @return RegistrationResult
     */
    private function verifiedResult(): RegistrationResult
    {
        return new RegistrationResult(
            'raw-id',
            'cred-encoded',
            "-----BEGIN PUBLIC KEY-----\nabc\n-----END PUBLIC KEY-----",
            0,
            null,
            'none',
            []
        );
    }
}
