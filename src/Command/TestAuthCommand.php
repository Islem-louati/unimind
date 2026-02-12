<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:test-auth')]
class TestAuthCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        
        $output->writeln('🔍 Test d\'authentification pour tous les comptes...');
        
        $testAccounts = [
            'psy@test.com',
            'etudiant@test.com', 
            'admin@test.com',
            'responsable@test.com'
        ];
        
        foreach ($testAccounts as $email) {
            $output->writeln("\n📧 Test: $email");
            
            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user) {
                $output->writeln('✅ Utilisateur trouvé');
                $output->writeln('📋 Rôle: ' . $user->getRole()->value);
                
                // Tester le mot de passe
                if ($this->passwordHasher->isPasswordValid($user, 'test123')) {
                    $output->writeln('✅ Mot de passe VALIDÉ');
                } else {
                    $output->writeln('❌ Mot de passe INVALIDE - Réinitialisation...');
                    $user->setPassword($this->passwordHasher->hashPassword($user, 'test123'));
                    $this->entityManager->flush();
                    $output->writeln('✅ Mot de passe réinitialisé');
                }
            } else {
                $output->writeln('❌ Utilisateur non trouvé');
            }
        }

        $output->writeln("\n🎉 Test terminé !");
        $output->writeln("\n📋 Comptes de test finaux :");
        $output->writeln('🔹 Psychologue: psy@test.com / test123');
        $output->writeln('🔹 Étudiant: etudiant@test.com / test123');
        $output->writeln('🔹 Admin: admin@test.com / test123');
        $output->writeln('🔹 Responsable: responsable@test.com / test123');

        return Command::SUCCESS;
    }
}
