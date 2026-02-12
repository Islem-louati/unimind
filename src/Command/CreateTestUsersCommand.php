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

#[AsCommand(name: 'app:create-test-users')]
class CreateTestUsersCommand extends Command
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
        // Créer un psychologue
        $psychologue = new User();
        $psychologue->setNom('Psychologue');
        $psychologue->setPrenom('Martin');
        $psychologue->setEmail('psycho@test.com');
        $psychologue->setRole(RoleType::PSYCHOLOGUE);
        $psychologue->setPassword($this->passwordHasher->hashPassword($psychologue, 'test123'));
        
        $this->entityManager->persist($psychologue);

        // Créer un étudiant
        $etudiant = new User();
        $etudiant->setNom('Etudiant');
        $etudiant->setPrenom('Jean');
        $etudiant->setEmail('etudiant@test.com');
        $etudiant->setRole(RoleType::ETUDIANT);
        $etudiant->setPassword($this->passwordHasher->hashPassword($etudiant, 'test123'));
        
        $this->entityManager->persist($etudiant);

        // Créer un admin
        $admin = new User();
        $admin->setNom('System');
        $admin->setPrenom('Admin');
        $admin->setEmail('admin@test.com');
        $admin->setRole(RoleType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'test123'));
        
        $this->entityManager->persist($admin);

        // Créer un responsable
        $responsable = new User();
        $responsable->setNom('Etudiant');
        $responsable->setPrenom('Responsable');
        $responsable->setEmail('resp@test.com');
        $responsable->setRole(RoleType::RESPONSABLE_ETUDIANT);
        $responsable->setPassword($this->passwordHasher->hashPassword($responsable, 'test123'));
        
        $this->entityManager->persist($responsable);

        $this->entityManager->flush();

        $output->writeln('✅ Utilisateurs de test créés avec succès !');
        $output->writeln('');
        $output->writeln('📋 Comptes de test :');
        $output->writeln('🔹 Psychologue: psycho@test.com / test123');
        $output->writeln('🔹 Étudiant: etudiant@test.com / test123');
        $output->writeln('🔹 Admin: admin@test.com / test123');
        $output->writeln('🔹 Responsable: resp@test.com / test123');

        return Command::SUCCESS;
    }
}
