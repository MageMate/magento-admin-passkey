<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use CBOR\CBOREncoder;
use MageMate\AdminPasskey\Exception\WebauthnException;

/**
 * Thin adapter over the 2tvenom/cborencode decoder.
 *
 * Isolating the concrete CBOR library here keeps the verifiers library-agnostic:
 * swapping to another decoder (or web-auth/webauthn-lib) only touches this class.
 */
class CborDecoder
{
    /**
     * Decode a CBOR byte string into a PHP value.
     *
     * @param string $binary
     * @return mixed
     * @throws WebauthnException
     */
    public function decode(string $binary)
    {
        try {
            // The library mutates the argument by reference, so use a local copy.
            $buffer = $binary;
            // @codingStandardsIgnoreStart
            $decoded = CBOREncoder::decode($buffer);
            // @codingStandardsIgnoreEnd
        } catch (\Throwable $e) {
            throw new WebauthnException(__('The authenticator data could not be decoded.'));
        }

        if ($decoded === null) {
            throw new WebauthnException(__('The authenticator data could not be decoded.'));
        }

        return $decoded;
    }
}
