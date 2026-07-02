<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Login;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Coarse per-client rate limiter for the anonymous login-options endpoint.
 *
 * Caps how many challenge-issuing requests a single remote address may make in a
 * rolling window so the passwordless endpoint cannot be used to hammer the
 * server or farm challenges. Backed by the shared cache; the window resets on
 * every allowed request (sliding), which is sufficient for abuse prevention.
 */
class RateLimiter
{
    /**
     * Cache key prefix for the per-client attempt counter.
     */
    private const CACHE_PREFIX = 'magemate_passkey_login_rl_';

    /**
     * Maximum allowed requests within the window.
     */
    private const MAX_ATTEMPTS = 15;

    /**
     * Rolling window length in seconds.
     */
    private const WINDOW_SECONDS = 60;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var RemoteAddress
     */
    private RemoteAddress $remoteAddress;

    /**
     * @param CacheInterface $cache
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(CacheInterface $cache, RemoteAddress $remoteAddress)
    {
        $this->cache = $cache;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Register an attempt and report whether it is within the allowed rate.
     *
     * @return bool True when the request may proceed, false when throttled.
     */
    public function allow(): bool
    {
        $key = self::CACHE_PREFIX . sha1((string)$this->remoteAddress->getRemoteAddress());
        $count = (int)$this->cache->load($key);
        if ($count >= self::MAX_ATTEMPTS) {
            return false;
        }

        $this->cache->save((string)($count + 1), $key, [], self::WINDOW_SECONDS);

        return true;
    }
}
