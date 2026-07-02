<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Webauthn\Internal;

use CBOR\CBOREncoder;
use CBOR\Types\CBORByteString;
use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CborDecoder;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CoseKeyConverter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Webauthn\Internal\CoseKeyConverter
 */
class CoseKeyConverterTest extends TestCase
{
    private CoseKeyConverter $converter;

    private CborDecoder $cborDecoder;

    protected function setUp(): void
    {
        $this->converter = new CoseKeyConverter();
        $this->cborDecoder = new CborDecoder();
    }

    public function testConvertsEs256KeyToVerifiablePem(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $details = openssl_pkey_get_details($key)['ec'];

        $cose = $this->decodeCose(CBOREncoder::encode([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => new CBORByteString(str_pad($details['x'], 32, "\x00", STR_PAD_LEFT)),
            -3 => new CBORByteString(str_pad($details['y'], 32, "\x00", STR_PAD_LEFT)),
        ]));

        $pem = $this->converter->toPem($cose);

        $this->assertSignatureVerifies($key, $pem);
    }

    public function testConvertsRs256KeyToVerifiablePem(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $details = openssl_pkey_get_details($key)['rsa'];

        $cose = $this->decodeCose(CBOREncoder::encode([
            1 => 3,
            3 => -257,
            -1 => new CBORByteString($details['n']),
            -2 => new CBORByteString($details['e']),
        ]));

        $pem = $this->converter->toPem($cose);

        $this->assertSignatureVerifies($key, $pem);
    }

    public function testRejectsUnsupportedKeyType(): void
    {
        $this->expectException(WebauthnException::class);
        $this->converter->toPem([1 => 99, 3 => -7]);
    }

    /**
     * @param mixed $key
     * @param string $pem
     * @return void
     */
    private function assertSignatureVerifies($key, string $pem): void
    {
        $message = 'cose-key-conversion-proof';
        $signature = '';
        openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);

        self::assertSame(1, openssl_verify($message, $signature, $pem, OPENSSL_ALGO_SHA256));
    }

    /**
     * @param string $binary
     * @return array
     */
    private function decodeCose(string $binary): array
    {
        /** @var array $cose */
        $cose = $this->cborDecoder->decode($binary);

        return $cose;
    }
}
