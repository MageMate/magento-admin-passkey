<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Plugin\Backend;

use MageMate\AdminPasskey\Model\Login\PasswordLoginPolicy;
use Magento\Backend\Model\Auth;
use Magento\Framework\Exception\AuthenticationException;

/**
 * Blocks password sign-in for admin users who own an active passkey.
 *
 * A `before` plugin on {@see Auth::login()} runs ahead of the method's own
 * try/catch, so the thrown {@see AuthenticationException} propagates straight to
 * the login controller, which renders the message. The passwordless login path
 * (US-007) does not call `Auth::login()` — it establishes the session directly —
 * so passkey sign-in is unaffected.
 */
class DisallowPasswordLogin
{
    /**
     * @var PasswordLoginPolicy
     */
    private PasswordLoginPolicy $policy;

    /**
     * @param PasswordLoginPolicy $policy
     */
    public function __construct(PasswordLoginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Reject password sign-in when the user must use a passkey.
     *
     * @param Auth $subject
     * @param string|null $username
     * @param string|null $password
     * @return void
     * @throws AuthenticationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeLogin(Auth $subject, $username, $password): void
    {
        if ($this->policy->isPasswordLoginBlocked((string) $username)) {
            throw new AuthenticationException(
                __(
                    'Password sign-in is disabled for this account. '
                    . 'Use the "Sign in with a passkey" button on the login screen.'
                )
            );
        }
    }
}
