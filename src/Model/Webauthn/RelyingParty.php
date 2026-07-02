<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Derives the WebAuthn Relying Party id/name/origin from configuration and the
 * admin store base URL.
 *
 * The RP id defaults to the base-URL host (the registrable domain the browser
 * will bind the credential to) and the origin to scheme://host[:port]. Both the
 * id and name may be overridden through system configuration for setups where
 * the admin runs on a dedicated sub-domain.
 */
class RelyingParty implements RelyingPartyInterface
{
    /**
     * Configuration paths (system.xml is introduced in a later story; reading an
     * undefined path simply yields null and falls back to the derived value).
     */
    public const XML_PATH_RP_ID = 'adminpasskey/general/rp_id';
    public const XML_PATH_RP_NAME = 'adminpasskey/general/rp_name';
    private const XML_PATH_STORE_NAME = 'general/store_information/name';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        $configured = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RP_ID, ScopeInterface::SCOPE_STORE));
        if ($configured !== '') {
            return $configured;
        }

        return $this->getHost();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        $configured = trim(
            (string)$this->scopeConfig->getValue(self::XML_PATH_RP_NAME, ScopeInterface::SCOPE_STORE)
        );
        if ($configured !== '') {
            return $configured;
        }

        $storeName = trim(
            (string)$this->scopeConfig->getValue(self::XML_PATH_STORE_NAME, ScopeInterface::SCOPE_STORE)
        );

        return $storeName !== '' ? $storeName : $this->getHost();
    }

    /**
     * @inheritDoc
     */
    public function getOrigin(): string
    {
        $parts = $this->parseBaseUrl();

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $origin = $scheme . '://' . $host;

        if (isset($parts['port'])
            && !($scheme === 'https' && (int)$parts['port'] === 443)
            && !($scheme === 'http' && (int)$parts['port'] === 80)
        ) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * Resolve the effective host from the admin store base URL.
     *
     * @return string
     */
    private function getHost(): string
    {
        return (string)($this->parseBaseUrl()['host'] ?? '');
    }

    /**
     * Parse the secure admin store base URL into its components.
     *
     * @return array
     */
    private function parseBaseUrl(): array
    {
        try {
            $store = $this->storeManager->getStore(Store::ADMIN_CODE);
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (\Throwable $e) {
            return [];
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parts = parse_url((string)$baseUrl);

        return is_array($parts) ? $parts : [];
    }
}
