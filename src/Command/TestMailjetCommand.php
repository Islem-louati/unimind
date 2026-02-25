<?php

namespace App\Command;

use App\Service\EmailManager;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-mailjet')]
class TestMailjetCommand extends Command
{
    public function __construct(
        private EmailManager $emailManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de test')
            ->setDescription('Test Mailjet email sending');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('<error>❌ Utilisateur non trouvé</error>');
            return Command::FAILURE;
        }

        $output->writeln('📧 Envoi de l\'email de test via Mailjet...');
        
        try {
            $this->emailManager->sendVerificationEmail($user);
            $output->writeln('<info>✅ Email envoyé avec succès !</info>');
            $output->writeln('Vérifiez vos statistiques sur https://app.mailjet.com');
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}