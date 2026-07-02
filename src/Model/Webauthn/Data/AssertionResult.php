<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn\Data;

/**
 * Verified output of an assertion (login) ceremony — the updated sign count to
 * persist and whether user verification was performed.
 */
class AssertionResult
{
    /**
     * @param int $newSignCount Signature counter reported by the authenticator.
     * @param bool $userVerified Whether the UV flag was set.
     */
    public function __construct(
        private readonly int $newSignCount,
        private readonly bool $userVerified
    ) {
    }

    /**
     * @return int
     */
    public function getNewSignCount(): int
    {
        return $this->newSignCount;
    }

    /**
     * @return bool
     */
    public function isUserVerified(): bool
    {
        return $this->userVerified;
    }
}
