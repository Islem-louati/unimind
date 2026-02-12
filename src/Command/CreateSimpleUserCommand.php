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

#[AsCommand(name: 'app:create-simple-user')]
class CreateSimpleUserCommand extends Command
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
        // Supprimer l'ancien utilisateur s'il existe
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($existingUser) {
            $this->entityManager->remove($existingUser);
        }

        // Créer un nouvel utilisateur simple
        $user = new User();
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setEmail('test@test.com');
        $user->setRole(RoleType::PSYCHOLOGUE);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test123'));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('✅ Utilisateur simple créé avec succès !');
        $output->writeln('📧 Email: test@test.com');
        $output->writeln('🔑 Mot de passe: test123');

        return Command::SUCCESS;
    }
}
