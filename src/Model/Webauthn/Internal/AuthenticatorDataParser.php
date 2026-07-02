<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use MageMate\AdminPasskey\Exception\WebauthnException;
use MageMate\AdminPasskey\Model\Webauthn\Data\AuthenticatorData;

/**
 * Parses the raw authenticator-data byte string into a structured value object.
 *
 * @see https://www.w3.org/TR/webauthn-2/#sctn-authenticator-data
 */
class AuthenticatorDataParser
{
    /**
     * Minimum length: 32 (rpIdHash) + 1 (flags) + 4 (signCount).
     */
    private const MIN_LENGTH = 37;

    /**
     * Attested credential data flag (bit 6).
     */
    private const FLAG_AT = 0x40;

    /**
     * @var CborDecoder
     */
    private CborDecoder $cborDecoder;

    /**
     * @param CborDecoder $cborDecoder
     */
    public function __construct(CborDecoder $cborDecoder)
    {
        $this->cborDecoder = $cborDecoder;
    }

    /**
     * Parse authenticator data bytes.
     *
     * @param string $bytes
     * @return AuthenticatorData
     * @throws WebauthnException
     */
    public function parse(string $bytes): AuthenticatorData
    {
        if (strlen($bytes) < self::MIN_LENGTH) {
            throw new WebauthnException(__('The authenticator data is malformed.'));
        }

        $rpIdHash = substr($bytes, 0, 32);
        $flags = ord($bytes[32]);
        $signCount = (int)unpack('N', substr($bytes, 33, 4))[1];

        if (($flags & self::FLAG_AT) === 0) {
            return new AuthenticatorData($rpIdHash, $flags, $signCount);
        }

        // Attested credential data: aaguid(16) + credIdLen(2) + credId + COSE key.
        if (strlen($bytes) < 55) {
            throw new WebauthnException(__('The attested credential data is malformed.'));
        }

        $aaguid = substr($bytes, 37, 16);
        $credentialIdLength = (int)unpack('n', substr($bytes, 53, 2))[1];

        $credentialId = substr($bytes, 55, $credentialIdLength);
        if (strlen($credentialId) !== $credentialIdLength) {
            throw new WebauthnException(__('The attested credential data is malformed.'));
        }

        $coseKey = $this->cborDecoder->decode(substr($bytes, 55 + $credentialIdLength));
        if (!is_array($coseKey)) {
            throw new WebauthnException(__('The credential public key is malformed.'));
        }

        return new AuthenticatorData($rpIdHash, $flags, $signCount, $aaguid, $credentialId, $coseKey);
    }
}
