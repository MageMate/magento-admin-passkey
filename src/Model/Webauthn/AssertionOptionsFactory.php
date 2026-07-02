<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\Webauthn;

use MageMate\AdminPasskey\Model\Webauthn\Data\AssertionOptions;
use MageMate\AdminPasskey\Model\Webauthn\Internal\CeremonyConfig;
use MageMate\AdminPasskey\Model\Webauthn\Internal\ChallengeGenerator;

/**
 * Default assertion options factory.
 *
 * With no allowed credentials the options drive a discoverable-credential
 * (passwordless) login where the browser offers the user's available passkeys
 * without any username being typed.
 */
class AssertionOptionsFactory implements AssertionOptionsFactoryInterface
{
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
     * @param RelyingPartyInterface $relyingParty
     * @param ChallengeGenerator $challengeGenerator
     * @param CeremonyConfig $ceremonyConfig
     */
    public function __construct(
        RelyingPartyInterface $relyingParty,
        ChallengeGenerator $challengeGenerator,
        CeremonyConfig $ceremonyConfig
    ) {
        $this->relyingParty = $relyingParty;
        $this->challengeGenerator = $challengeGenerator;
        $this->ceremonyConfig = $ceremonyConfig;
    }

    /**
     * @inheritDoc
     */
    public function create(array $allowCredentialIds = []): AssertionOptions
    {
        $allowCredentials = [];
        foreach ($allowCredentialIds as $credentialId) {
            $allowCredentials[] = [
                'type' => 'public-key',
                'id' => $credentialId,
            ];
        }

        return new AssertionOptions(
            $this->challengeGenerator->generate(),
            $this->relyingParty->getId(),
            $allowCredentials,
            $this->ceremonyConfig->getUserVerificationRequirement(),
            $this->ceremonyConfig->getTimeout()
        );
    }
}
