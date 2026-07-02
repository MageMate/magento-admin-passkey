<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Registration;

use Magento\Backend\Model\Session;

/**
 * Session-bound store for the in-flight registration challenge.
 *
 * The challenge is single-use and pinned to the admin session and the user it
 * was issued for, so a challenge issued to one admin can never be replayed by
 * another. Never trust a client-supplied challenge — only the value stored here.
 */
class ChallengeStorage
{
    /**
     * Session key holding the pending registration ceremony state.
     */
    private const SESSION_KEY = 'magemate_passkey_registration';

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
     * Persist the challenge issued for the given admin user.
     *
     * @param int $userId
     * @param string $challenge Base64url challenge.
     * @return void
     */
    public function store(int $userId, string $challenge): void
    {
        $this->session->setData(self::SESSION_KEY, [
            'user_id' => $userId,
            'challenge' => $challenge,
        ]);
    }

    /**
     * Return the challenge stored for the given user.
     *
     * Yields null when absent or when issued for a different user.
     *
     * @param int $userId
     * @return string|null
     */
    public function get(int $userId): ?string
    {
        $data = $this->session->getData(self::SESSION_KEY);
        if (!is_array($data)
            || (int)($data['user_id'] ?? 0) !== $userId
            || !is_string($data['challenge'] ?? null)
            || $data['challenge'] === ''
        ) {
            return null;
        }

        return $data['challenge'];
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
