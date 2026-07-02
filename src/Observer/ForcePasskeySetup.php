<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Observer;

use MageMate\AdminPasskey\Controller\Adminhtml\Register\Index as RegisterIndex;
use MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * Redirects admins to passkey registration when force-setup applies.
 *
 * Runs on `controller_action_predispatch` after Two-Factor Auth (module
 * sequence) so a pending 2FA prompt keeps priority. Our own controllers,
 * the auth login/logout routes and the TFA routes are allow-listed to avoid
 * a redirect loop and to leave 2FA flows untouched.
 */
class ForcePasskeySetup implements ObserverInterface
{
    /**
     * Full action name prefixes that must never be intercepted.
     *
     * `passkey_` covers our own register/login/manage controllers (so the
     * redirect target is reachable — no loop); `tfa_` leaves Two-Factor Auth
     * flows alone; the explicit entries cover login, logout and password reset.
     *
     * @var string[]
     */
    private const ALLOWED_PREFIXES = ['passkey_', 'tfa_'];

    /**
     * @var string[]
     */
    private const ALLOWED_ACTIONS = [
        'adminhtml_auth_login',
        'adminhtml_auth_logout',
        'adminhtml_auth_forgotpassword',
        'adminhtml_auth_resetpassword',
        'adminhtml_auth_resetpasswordpost',
    ];

    /**
     * @var SetupRequirement
     */
    private SetupRequirement $setupRequirement;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @var ActionFlag
     */
    private ActionFlag $actionFlag;

    /**
     * @var UrlInterface
     */
    private UrlInterface $url;

    /**
     * @param SetupRequirement $setupRequirement
     * @param UserContextInterface $userContext
     * @param AuthorizationInterface $authorization
     * @param ActionFlag $actionFlag
     * @param UrlInterface $url
     */
    public function __construct(
        SetupRequirement $setupRequirement,
        UserContextInterface $userContext,
        AuthorizationInterface $authorization,
        ActionFlag $actionFlag,
        UrlInterface $url
    ) {
        $this->setupRequirement = $setupRequirement;
        $this->userContext = $userContext;
        $this->authorization = $authorization;
        $this->actionFlag = $actionFlag;
        $this->url = $url;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $fullActionName = (string)$observer->getEvent()->getData('request')->getFullActionName();
        if ($this->isAllowed($fullActionName)) {
            return;
        }

        // Only force-setup admins who can actually register a passkey.
        if (!$this->authorization->isAllowed(RegisterIndex::ADMIN_RESOURCE)) {
            return;
        }

        $userId = (int)$this->userContext->getUserId();
        if (!$this->setupRequirement->isRequiredFor($userId)) {
            return;
        }

        /** @var Action $controllerAction */
        $controllerAction = $observer->getEvent()->getData('controller_action');
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
        $controllerAction->getResponse()->setRedirect($this->url->getUrl('passkey/register'));
    }

    /**
     * Whether the current action is exempt from force-setup redirection.
     *
     * @param string $fullActionName
     * @return bool
     */
    private function isAllowed(string $fullActionName): bool
    {
        if (in_array($fullActionName, self::ALLOWED_ACTIONS, true)) {
            return true;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (strpos($fullActionName, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
