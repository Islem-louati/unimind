<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:debug-auth')]
class DebugAuthCommand extends Command
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
        
        $output->writeln('🔍 Debug complet de l\'authentification...');
        
        // Récupérer l'utilisateur psychologue
        $user = $userRepository->findOneBy(['email' => 'psycho@test.com']);
        
        if (!$user) {
            $output->writeln('❌ Utilisateur non trouvé');
            return Command::FAILURE;
        }
        
        $output->writeln('✅ Utilisateur: ' . $user->getEmail());
        $output->writeln('📋 ID: ' . $user->getUserId());
        $output->writeln('📋 Nom: ' . $user->getNom() . ' ' . $user->getPrenom());
        $output->writeln('📋 Rôle: ' . $user->getRole()->value);
        $output->writeln('🔑 Rôles Symfony: ' . implode(', ', $user->getRoles()));
        $output->writeln('🔐 Hash du mot de passe: ' . substr($user->getPassword(), 0, 20) . '...');
        
        // Test avec différents mots de passe
        $passwords = ['test123', '123', 'admin', 'password'];
        
        foreach ($passwords as $pwd) {
            $output->writeln("\n🔍 Test avec mot de passe: '$pwd'");
            if ($this->passwordHasher->isPasswordValid($user, $pwd)) {
                $output->writeln('✅ VALIDÉ avec: ' . $pwd);
                break;
            } else {
                $output->writeln('❌ INVALIDE');
            }
        }
        
        // Réinitialiser avec un nouveau mot de passe
        $output->writeln("\n🔄 Réinitialisation avec 'test123'...");
        $newHash = $this->passwordHasher->hashPassword($user, 'test123');
        $user->setPassword($newHash);
        $this->entityManager->flush();
        
        $output->writeln('✅ Nouveau hash: ' . substr($newHash, 0, 20) . '...');
        
        // Tester à nouveau
        if ($this->passwordHasher->isPasswordValid($user, 'test123')) {
            $output->writeln('✅ Mot de passe maintenant VALIDÉ');
        } else {
            $output->writeln('❌ Toujours invalide');
        }
        
        // Vérifier les interfaces
        $output->writeln("\n🔍 Vérification des interfaces:");
        $output->writeln('📋 UserInterface: ' . ($user instanceof \Symfony\Component\Security\Core\User\UserInterface ? '✅' : '❌'));
        $output->writeln('📋 PasswordAuthenticatedUserInterface: ' . ($user instanceof \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface ? '✅' : '❌'));
        
        return Command::SUCCESS;
    }
}
