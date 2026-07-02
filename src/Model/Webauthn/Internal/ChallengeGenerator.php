<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Internal;

use MageMate\AdminPasskey\Exception\WebauthnException;

/**
 * Generates cryptographically strong, single-use WebAuthn challenges.
 */
class ChallengeGenerator
{
    /**
     * Challenge length in bytes (32 bytes / 256 bits, well above the 16-byte minimum).
     */
    private const CHALLENGE_BYTES = 32;

    /**
     * @var Base64Url
     */
    private Base64Url $base64Url;

    /**
     * @param Base64Url $base64Url
     */
    public function __construct(Base64Url $base64Url)
    {
        $this->base64Url = $base64Url;
    }

    /**
     * Produce a fresh base64url-encoded challenge.
     *
     * @return string
     * @throws WebauthnException
     */
    public function generate(): string
    {
        try {
            $random = random_bytes(self::CHALLENGE_BYTES);
        } catch (\Throwable $e) {
            throw new WebauthnException(__('A secure challenge could not be generated.'));
        }

        return $this->base64Url->encode($random);
    }
}
