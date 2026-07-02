<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use MageMate\AdminPasskey\Exception\WebauthnException;

/**
 * Converts a COSE_Key (RFC 8152) into a PEM SubjectPublicKeyInfo that openssl
 * can verify against.
 *
 * Supported algorithms: ES256 (ECDSA P-256) and RS256 (RSASSA-PKCS1-v1_5 with
 * SHA-256). Together these cover effectively every shipping platform/roaming
 * authenticator; unsupported COSE keys are rejected rather than silently
 * downgraded.
 *
 * @see https://www.iana.org/assignments/cose/cose.xhtml
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
class CoseKeyConverter
{
    /**
     * COSE key common parameters.
     */
    private const COSE_KTY = 1;
    private const COSE_ALG = 3;

    /**
     * COSE EC2 (elliptic curve) key parameters.
     */
    private const COSE_EC2_CRV = -1;
    private const COSE_EC2_X = -2;
    private const COSE_EC2_Y = -3;

    /**
     * COSE RSA key parameters.
     */
    private const COSE_RSA_N = -1;
    private const COSE_RSA_E = -2;

    /**
     * Key types.
     */
    private const KTY_EC2 = 2;
    private const KTY_RSA = 3;

    /**
     * Algorithm identifiers.
     */
    public const ALG_ES256 = -7;
    public const ALG_RS256 = -257;

    /**
     * NIST P-256 curve identifier.
     */
    private const CRV_P256 = 1;

    /**
     * Convert a decoded COSE key map to a PEM public key.
     *
     * @param array $cose Decoded COSE key (integer-keyed map).
     * @return string PEM-encoded SubjectPublicKeyInfo.
     * @throws WebauthnException
     */
    public function toPem(array $cose): string
    {
        $kty = $this->intValue($cose[self::COSE_KTY] ?? null);

        if ($kty === self::KTY_EC2) {
            return $this->ec2ToPem($cose);
        }

        if ($kty === self::KTY_RSA) {
            return $this->rsaToPem($cose);
        }

        throw new WebauthnException(__('The credential uses an unsupported key type.'));
    }

    /**
     * Build a PEM public key from an EC2 (ES256 / P-256) COSE key.
     *
     * @param array $cose
     * @return string
     * @throws WebauthnException
     */
    private function ec2ToPem(array $cose): string
    {
        if ($this->intValue($cose[self::COSE_ALG] ?? null) !== self::ALG_ES256
            || $this->intValue($cose[self::COSE_EC2_CRV] ?? null) !== self::CRV_P256
        ) {
            throw new WebauthnException(__('The credential uses an unsupported EC algorithm.'));
        }

        $x = $this->byteValue($cose[self::COSE_EC2_X] ?? null);
        $y = $this->byteValue($cose[self::COSE_EC2_Y] ?? null);
        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw new WebauthnException(__('The credential public key is malformed.'));
        }

        // Uncompressed point (0x04 || X || Y) wrapped in an id-ecPublicKey /
        // prime256v1 SubjectPublicKeyInfo (fixed DER prefix per RFC 5480).
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
            . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00"
            . "\x04" . $x . $y;

        return $this->derToPem($der);
    }

    /**
     * Build a PEM public key from an RSA (RS256) COSE key.
     *
     * @param array $cose
     * @return string
     * @throws WebauthnException
     */
    private function rsaToPem(array $cose): string
    {
        if ($this->intValue($cose[self::COSE_ALG] ?? null) !== self::ALG_RS256) {
            throw new WebauthnException(__('The credential uses an unsupported RSA algorithm.'));
        }

        $modulus = $this->byteValue($cose[self::COSE_RSA_N] ?? null);
        $exponent = $this->byteValue($cose[self::COSE_RSA_E] ?? null);
        if ($modulus === '' || $exponent === '') {
            throw new WebauthnException(__('The credential public key is malformed.'));
        }

        $rsaPublicKey = $this->derSequence(
            $this->derInteger($modulus) . $this->derInteger($exponent)
        );

        // AlgorithmIdentifier for rsaEncryption (1.2.840.113549.1.1.1) + NULL.
        $algorithmId = $this->derSequence(
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00"
        );

        $der = $this->derSequence(
            $algorithmId . $this->derBitString($rsaPublicKey)
        );

        return $this->derToPem($der);
    }

    /**
     * Wrap DER bytes into a PEM public key block.
     *
     * @param string $der
     * @return string
     */
    private function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\r\n"
            . chunk_split(base64_encode($der), 64)
            . "-----END PUBLIC KEY-----\r\n";
    }

    /**
     * DER-encode a positive integer from a big-endian byte string.
     *
     * @param string $bytes
     * @return string
     */
    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        // Prepend a zero byte when the high bit is set to keep the value positive.
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->derLength(strlen($bytes)) . $bytes;
    }

    /**
     * DER-encode a SEQUENCE around already-encoded contents.
     *
     * @param string $contents
     * @return string
     */
    private function derSequence(string $contents): string
    {
        return "\x30" . $this->derLength(strlen($contents)) . $contents;
    }

    /**
     * DER-encode a BIT STRING (with a zero "unused bits" octet).
     *
     * @param string $contents
     * @return string
     */
    private function derBitString(string $contents): string
    {
        $contents = "\x00" . $contents;

        return "\x03" . $this->derLength(strlen($contents)) . $contents;
    }

    /**
     * Encode a DER length field.
     *
     * @param int $length
     * @return string
     */
    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Normalise a COSE integer value.
     *
     * @param mixed $value
     * @return int|null
     */
    private function intValue($value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * Extract raw bytes from a COSE value that may be a CBORByteString wrapper.
     *
     * @param mixed $value
     * @return string
     */
    private function byteValue($value): string
    {
        if (is_object($value) && method_exists($value, 'get_byte_string')) {
            return (string)$value->get_byte_string();
        }

        return is_string($value) ? $value : '';
    }
}
