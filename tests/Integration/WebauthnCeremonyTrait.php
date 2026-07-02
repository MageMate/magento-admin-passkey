<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Integration;

use CBOR\CBOREncoder;
use CBOR\Types\CBORByteString;
use OpenSSLAsymmetricKey;

/**
 * Crafts real WebAuthn ceremony material (EC P-256 keys, authenticator data,
 * client data and ECDSA signatures) so the integration tests exercise the full
 * verifier + repository stack against genuine crypto rather than mocks.
 */
trait WebauthnCeremonyTrait
{
    /**
     * Authenticator data flag: user present.
     *
     * @var int
     */
    private int $flagUserPresent = 0x01;

    /**
     * Authenticator data flag: user verified.
     *
     * @var int
     */
    private int $flagUserVerified = 0x04;

    /**
     * Authenticator data flag: attested credential data present.
     *
     * @var int
     */
    private int $flagAttested = 0x40;

    /**
     * Generate a fresh EC P-256 (ES256) key pair.
     *
     * @return OpenSSLAsymmetricKey
     */
    private function createEcKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if ($key === false) {
            self::fail('Unable to generate an EC key pair for the fixture.');
        }

        return $key;
    }

    /**
     * Return the PEM public key for an EC key pair.
     *
     * @param OpenSSLAsymmetricKey $key
     * @return string
     */
    private function publicPem(OpenSSLAsymmetricKey $key): string
    {
        return openssl_pkey_get_details($key)['key'];
    }

    /**
     * Encode raw bytes as an unpadded base64url string.
     *
     * @param string $raw
     * @return string
     */
    private function encodeBase64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Build clientDataJSON for a ceremony.
     *
     * @param string $type
     * @param string $challengeB64
     * @param string $origin
     * @return string
     */
    private function buildClientDataJson(string $type, string $challengeB64, string $origin): string
    {
        return (string)json_encode([
            'type' => $type,
            'challenge' => $challengeB64,
            'origin' => $origin,
        ]);
    }

    /**
     * Build authenticator data with no attested credential data (assertion).
     *
     * @param string $rpId
     * @param int $flags
     * @param int $signCount
     * @return string
     */
    private function buildAssertionAuthData(string $rpId, int $flags, int $signCount): string
    {
        return hash('sha256', $rpId, true) . chr($flags) . pack('N', $signCount);
    }

    /**
     * Encode an ES256 COSE key from an EC key pair.
     *
     * @param OpenSSLAsymmetricKey $key
     * @return string
     */
    private function buildCoseEs256Key(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key)['ec'];

        return (string)CBOREncoder::encode([
            1 => 2,   // kty: EC2
            3 => -7,  // alg: ES256
            -1 => 1,  // crv: P-256
            -2 => new CBORByteString(str_pad($details['x'], 32, "\x00", STR_PAD_LEFT)),
            -3 => new CBORByteString(str_pad($details['y'], 32, "\x00", STR_PAD_LEFT)),
        ]);
    }

    /**
     * Build authenticator data with attested credential data (registration).
     *
     * @param string $rpId
     * @param int $flags
     * @param int $signCount
     * @param string $credentialId
     * @param string $coseKey
     * @param string $aaguid
     * @return string
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    private function buildRegistrationAuthData(
        string $rpId,
        int $flags,
        int $signCount,
        string $credentialId,
        string $coseKey,
        string $aaguid
    ): string {
        return hash('sha256', $rpId, true)
            . chr($flags)
            . pack('N', $signCount)
            . $aaguid
            . pack('n', strlen($credentialId))
            . $credentialId
            . $coseKey;
    }

    /**
     * Encode a CBOR attestation object with fmt "none".
     *
     * @param string $authData
     * @return string
     */
    private function buildAttestationObject(string $authData): string
    {
        return (string)CBOREncoder::encode([
            'fmt' => 'none',
            'attStmt' => [],
            'authData' => new CBORByteString($authData),
        ]);
    }

    /**
     * Sign data with an EC private key (produces a DER ECDSA signature).
     *
     * @param string $data
     * @param OpenSSLAsymmetricKey $key
     * @return string
     */
    private function signEs256(string $data, OpenSSLAsymmetricKey $key): string
    {
        $signature = '';
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);

        return $signature;
    }
}
