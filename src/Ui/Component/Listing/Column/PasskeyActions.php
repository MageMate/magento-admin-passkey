<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Ui\Component\Listing\Column;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Per-row actions (delete) for the passkey management grid.
 */
class PasskeyActions extends Column
{
    private const URL_PATH_DELETE = 'passkey/manage/delete';

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
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
        }
        unset($item);

        return $dataSource;
    }
}
