<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user account',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Admin email address')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Admin password (will prompt if not provided)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        // Get email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Please enter the admin email: ');
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Email cannot be empty');
                }
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email format');
                }
                return $value;
            });
            $email = $helper->ask($input, $output, $question);
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            if ($existingUser->isAdmin()) {
                $io->warning(sprintf('Admin user "%s" already exists.', $email));
                return Command::SUCCESS;
            }

            // Upgrade existing user to admin
            $io->note(sprintf('User "%s" exists. Upgrading to admin...', $email));
            $existingUser->setAccountType(User::ACCOUNT_TYPE_ADMIN);
            $existingUser->setRoles(array_unique(array_merge($existingUser->getRoles(), ['ROLE_ADMIN'])));
            $existingUser->setIsVerified(true);
            $this->userRepository->save($existingUser, true);

            $io->success(sprintf('User "%s" has been upgraded to admin.', $email));
            return Command::SUCCESS;
        }

        // Get password
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Please enter the admin password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Password cannot be empty');
                }
                if (strlen($value) < 8) {
                    throw new \RuntimeException('Password must be at least 8 characters');
                }
                return $value;
            });
            $password = $helper->ask($input, $output, $question);

            // Confirm password
            $confirmQuestion = new Question('Please confirm the password: ');
            $confirmQuestion->setHidden(true);
            $confirmQuestion->setHiddenFallback(false);
            $confirmPassword = $helper->ask($input, $output, $confirmQuestion);

            if ($password !== $confirmPassword) {
                $io->error('Passwords do not match.');
                return Command::FAILURE;
            }
        }

        // Create admin user
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $user->setAccountType(User::ACCOUNT_TYPE_ADMIN);

        $this->userRepository->save($user, true);

        $io->success(sprintf('Admin user "%s" has been created successfully.', $email));

        return Command::SUCCESS;
    }
}