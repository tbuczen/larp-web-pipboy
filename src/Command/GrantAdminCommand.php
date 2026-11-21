<?php

declare(strict_types=1);
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Promotes an existing account (for example one created through /register
 * or Discord) to superadministrator, which includes the organizer role.
 */
#[AsCommand(name: 'app:grant-admin', description: 'Grant superadministrator access to an existing account.')]
final class GrantAdminCommand extends Command
{
    private const ROLE = 'ROLE_SUPER_ADMIN';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the existing account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $io->error('No account uses this email. Register it first, or create one with app:create-user --admin.');

            return Command::FAILURE;
        }

        if (in_array(self::ROLE, $user->getRoles(), true)) {
            $io->note('This account is already a superadministrator.');

            return Command::SUCCESS;
        }

        $user->setRoles([...$user->getRoles(), self::ROLE]);
        $this->entityManager->flush();
        $io->success('Superadministrator access granted. Sign in again to use the admin panel.');

        return Command::SUCCESS;
    }
}
