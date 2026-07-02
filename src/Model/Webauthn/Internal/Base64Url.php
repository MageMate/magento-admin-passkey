<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

/**
 * Base64url (RFC 4648 §5, unpadded) codec used across the WebAuthn ceremonies.
 *
 * The WebAuthn client serialises challenges and credential ids as base64url
 * without padding; keeping the encoding in one place avoids subtle mismatches
 * when comparing the server-generated challenge with the client response.
 */
class Base64Url
{
    /**
     * Encode raw bytes as unpadded base64url.
     *
     * @param string $binary
     * @return string
     */
    public function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * Decode an unpadded base64url string back to raw bytes.
     *
     * @param string $encoded
     * @return string
     */
    public function decode(string $encoded): string
    {
        $padded = strtr($encoded, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        return (string)base64_decode($padded, true);
    }
}
