<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Login;

use Magento\Backend\Model\Session;

/**
 * Session-bound store for the in-flight passwordless-login challenge.
 *
 * Unlike registration the login ceremony is user-agnostic (discoverable
 * credential) so the challenge is not pinned to any user; it is still single-use
 * and lives only in the admin session. Never trust a client-supplied challenge —
 * only the value stored here.
 */
class LoginChallengeStorage
{
    /**
     * Session key holding the pending login ceremony challenge.
     */
    private const SESSION_KEY = 'magemate_passkey_login_challenge';

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Persist the challenge issued for a login ceremony.
     *
     * @param string $challenge Base64url challenge.
     * @return void
     */
    public function store(string $challenge): void
    {
        $this->session->setData(self::SESSION_KEY, $challenge);
    }

    /**
     * Return the stored challenge, or null when none is pending.
     *
     * @return string|null
     */
    public function get(): ?string
    {
        $challenge = $this->session->getData(self::SESSION_KEY);
        if (!is_string($challenge) || $challenge === '') {
            return null;
        }

        return $challenge;
    }

    /**
     * Discard any pending challenge (single-use enforcement).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->session->unsetData(self::SESSION_KEY);
    }
}
