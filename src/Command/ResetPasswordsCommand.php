<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Enum\RoleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:reset-passwords')]
class ResetPasswordsCommand extends Command
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
        
        // Réinitialiser le mot de passe du psychologue
        $psychologue = $userRepository->findOneBy(['email' => 'psycho@test.com']);
        if ($psychologue) {
            $psychologue->setPassword($this->passwordHasher->hashPassword($psychologue, 'test123'));
            $output->writeln('✅ Mot de passe réinitialisé pour: psycho@test.com');
        }

        // Réinitialiser le mot de passe de l'étudiant
        $etudiant = $userRepository->findOneBy(['email' => 'etudiant@test.com']);
        if ($etudiant) {
            $etudiant->setPassword($this->passwordHasher->hashPassword($etudiant, 'test123'));
            $output->writeln('✅ Mot de passe réinitialisé pour: etudiant@test.com');
        }

        // Réinitialiser le mot de passe de l'admin
        $admin = $userRepository->findOneBy(['email' => 'admin@test.com']);
        if ($admin) {
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'test123'));
            $output->writeln('✅ Mot de passe réinitialisé pour: admin@test.com');
        }

        // Réinitialiser le mot de passe du responsable
        $responsable = $userRepository->findOneBy(['email' => 'responsable@test.com']);
        if ($responsable) {
            $responsable->setPassword($this->passwordHasher->hashPassword($responsable, 'test123'));
            $output->writeln('✅ Mot de passe réinitialisé pour: responsable@test.com');
        }

        $this->entityManager->flush();

        $output->writeln('');
        $output->writeln('🎉 Tous les mots de passe ont été réinitialisés avec succès !');
        $output->writeln('');
        $output->writeln('📋 Comptes de test :');
        $output->writeln('🔹 Psychologue: psycho@test.com / test123');
        $output->writeln('🔹 Étudiant: etudiant@test.com / test123');
        $output->writeln('🔹 Admin: admin@test.com / test123');
        $output->writeln('🔹 Responsable: responsable@test.com / test123');

        return Command::SUCCESS;
    }
}
