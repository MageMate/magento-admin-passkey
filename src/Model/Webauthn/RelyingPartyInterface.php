<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

/**
 * Describes the WebAuthn Relying Party (this Magento admin) for a ceremony.
 *
 * The id/name/origin are derived from configuration and the store base URL so
 * that callers never hard-code a domain.
 */
interface RelyingPartyInterface
{
    /**
     * Relying Party id — the effective domain the credential is scoped to.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Human-readable Relying Party name shown by the authenticator.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Expected origin (scheme://host[:port]) the client data must match.
     *
     * @return string
     */
    public function getOrigin(): string;
}
