<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Console\Command;

use MageMate\AdminPasskey\Api\PasskeyRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Emergency recovery for a locked-out admin (decision D8).
 *
 * When `disallow_password_login` is on, an admin who owns an active passkey but
 * can no longer use the authenticator (lost/broken device) cannot sign in with a
 * password either. A super-admin with shell access runs this command to
 * deactivate that user's passkeys, which re-opens password login for them —
 * password login is only blocked while the user owns an active passkey.
 */
class RecoverPasswordLogin extends Command
{
    private const ARG_USER = 'user';

    /**
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * @var UserResource
     */
    private UserResource $userResource;

    /**
     * @var PasskeyRepositoryInterface
     */
    private PasskeyRepositoryInterface $passkeyRepository;

    /**
     * @param UserFactory $userFactory
     * @param UserResource $userResource
     * @param PasskeyRepositoryInterface $passkeyRepository
     * @param string|null $name
     */
    public function __construct(
        UserFactory $userFactory,
        UserResource $userResource,
        PasskeyRepositoryInterface $passkeyRepository,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->userFactory = $userFactory;
        $this->userResource = $userResource;
        $this->passkeyRepository = $passkeyRepository;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('security:passkey:recover');
        $this->setDescription(
            'Restore admin password login for a user locked out by disallow_password_login '
            . '(deactivates that user\'s passkeys).'
        );
        $this->addArgument(self::ARG_USER, InputArgument::REQUIRED, 'Admin username');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getArgument(self::ARG_USER);

        $user = $this->userFactory->create();
        $this->userResource->load($user, $username, 'username');
        if (!$user->getId()) {
            throw new LocalizedException(__('Unknown admin user "%1".', $username));
        }

        $count = $this->passkeyRepository->deactivateForUser((int) $user->getId());

        if ($count === 0) {
            $output->writeln(
                (string) __('No active passkeys for "%1"; password login is already available.', $username)
            );

            return 0;
        }

        $output->writeln(
            (string) __(
                'Deactivated %1 passkey(s) for "%2". Password login is restored for this user.',
                $count,
                $username
            )
        );

        return 0;
    }
}
