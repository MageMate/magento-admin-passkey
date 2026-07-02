<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Model\Webauthn\Data\RegistrationOptions;
use MageMate\AdminPasskey\Model\Webauthn\Internal\Base64Url;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CeremonyConfig;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ChallengeGenerator;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CoseKeyConverter;

/**
 * Default registration options factory.
 *
 * Requests resident (discoverable) credentials so the resulting passkey can be
 * used for passwordless login, and advertises the algorithms the verifier can
 * actually check (ES256, RS256). Attestation is "none" by default (privacy).
 */
class RegistrationOptionsFactory implements RegistrationOptionsFactoryInterface
{
    /**
     * Default attestation conveyance preference.
     */
    private const ATTESTATION = 'none';

    /**
     * @var RelyingPartyInterface
     */
    private RelyingPartyInterface $relyingParty;

    /**
     * @var ChallengeGenerator
     */
    private ChallengeGenerator $challengeGenerator;

    /**
     * @var CeremonyConfig
     */
    private CeremonyConfig $ceremonyConfig;

    /**
     * @var Base64Url
     */
    private Base64Url $base64Url;

    /**
     * @param RelyingPartyInterface $relyingParty
     * @param ChallengeGenerator $challengeGenerator
     * @param CeremonyConfig $ceremonyConfig
     * @param Base64Url $base64Url
     */
    public function __construct(
        RelyingPartyInterface $relyingParty,
        ChallengeGenerator $challengeGenerator,
        CeremonyConfig $ceremonyConfig,
        Base64Url $base64Url
    ) {
        $this->relyingParty = $relyingParty;
        $this->challengeGenerator = $challengeGenerator;
        $this->ceremonyConfig = $ceremonyConfig;
        $this->base64Url = $base64Url;
    }

    /**
     * @inheritDoc
     */
    public function create(
        int $userId,
        string $userName,
        string $displayName,
        array $excludeCredentialIds = []
    ): RegistrationOptions {
        $excludeCredentials = [];
        foreach ($excludeCredentialIds as $credentialId) {
            $excludeCredentials[] = [
                'type' => 'public-key',
                'id' => $credentialId,
            ];
        }

        return new RegistrationOptions(
            $this->challengeGenerator->generate(),
            [
                'id' => $this->relyingParty->getId(),
                'name' => $this->relyingParty->getName(),
            ],
            [
                // Stable opaque 8-byte user handle (big-endian admin user id).
                'id' => $this->base64Url->encode(pack('J', $userId)),
                'name' => $userName,
                'displayName' => $displayName !== '' ? $displayName : $userName,
            ],
            [
                ['type' => 'public-key', 'alg' => CoseKeyConverter::ALG_ES256],
                ['type' => 'public-key', 'alg' => CoseKeyConverter::ALG_RS256],
            ],
            $excludeCredentials,
            self::ATTESTATION,
            $this->ceremonyConfig->getUserVerificationRequirement(),
            $this->ceremonyConfig->getTimeout()
        );
    }
}
