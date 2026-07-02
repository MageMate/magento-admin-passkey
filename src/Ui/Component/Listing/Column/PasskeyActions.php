<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Ui\Component\Listing\Column;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Model\Passkey\Source\Status;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Per-row actions (delete, re-register) for the passkey management grid.
 */
class PasskeyActions extends Column
{
    private const URL_PATH_DELETE = 'passkey/manage/delete';
    private const URL_PATH_REGISTER = 'passkey/register/index';

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var AuthSession
     */
    private AuthSession $authSession;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthSession $authSession
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        AuthSession $authSession,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
        $this->authSession = $authSession;
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');
        $currentUserId = $this->getCurrentUserId();
        foreach ($dataSource['data']['items'] as &$item) {
            $passkeyId = $item[PasskeyInterface::PASSKEY_ID] ?? null;
            if ($passkeyId === null) {
                continue;
            }

            $label = (string)($item[PasskeyInterface::LABEL] ?? '');
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::URL_PATH_DELETE,
                    [PasskeyInterface::PASSKEY_ID => $passkeyId]
                ),
                'label' => __('Delete'),
                'isAjax' => true,
                'post' => true,
                'confirm' => [
                    'title' => __('Delete passkey'),
                    'message' => __('Are you sure you want to delete "%1"?', $label),
                    '__disableTmpl' => true,
                ],
            ];

            if ($this->canReRegister($item, $currentUserId)) {
                $item[$name]['reregister'] = [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_REGISTER),
                    'label' => __('Re-register'),
                ];
            }
        }
        unset($item);

        return $dataSource;
    }

    /**
     * Whether to prompt the current admin to re-register for this row.
     *
     * Only their own expired passkeys get the prompt — re-registration always
     * enrolls a new credential for the signed-in admin, so it is meaningless to
     * offer it against another user's row (visible only with manage_all).
     *
     * @param array $item
     * @param int $currentUserId
     * @return bool
     */
    private function canReRegister(array $item, int $currentUserId): bool
    {
        $isExpired = ($item['status'] ?? null) === Status::EXPIRED;
        $isOwnRow = (int)($item[PasskeyInterface::USER_ID] ?? 0) === $currentUserId;

        return $currentUserId > 0 && $isExpired && $isOwnRow;
    }

    /**
     * Resolve the currently signed-in admin's user id (0 when unresolved).
     *
     * @return int
     */
    private function getCurrentUserId(): int
    {
        $user = $this->authSession->getUser();

        return $user !== null ? (int)$user->getId() : 0;
    }
}
