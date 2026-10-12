<?php

declare(strict_types=1);
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-user', description: 'Create an account; prompts securely for the password.')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant superadministrator access');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->error('An account with this email already exists.');
            return Command::FAILURE;
        }
        if (!$input->isInteractive()) {
            $io->error('Use an interactive terminal to supply the password securely.');
            return Command::FAILURE;
        }
        $password = $io->askHidden('Password (at least 12 characters)', static function (?string $value): string {
            if ($value === null || mb_strlen($value) < 12) {
                throw new \InvalidArgumentException('Use at least 12 characters.');
            }
            return $value;
        });
        $user = (new User())->setEmail($email)->setRoles($input->getOption('admin') ? ['ROLE_SUPER_ADMIN'] : ['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error((string) $errors);
            return Command::FAILURE;
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success('Account created.');
        return Command::SUCCESS;
    }
}
