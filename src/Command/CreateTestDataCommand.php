<?php

namespace App\Command;

use App\Entity\SuiviTraitement;
use App\Entity\Traitement;
use App\Entity\User;
use App\Entity\Enum\Ressenti;
use App\Entity\Enum\SaisiPar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:create-test-data')]
class CreateTestDataCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔧 Création de données de test complètes...');
        
        // Supprimer les anciennes données
        $this->cleanupOldData($output);
        
        // Créer les utilisateurs de test
        $users = $this->createTestUsers($output);
        
        // Créer les traitements
        $traitements = $this->createTestTraitements($users, $output);
        
        // Créer les suivis
        $this->createTestSuivis($traitements, $output);
        
        $output->writeln('✅ Données de test créées avec succès !');
        $output->writeln('');
        $output->writeln('🚀 Vous pouvez maintenant tester :');
        $output->writeln('   - http://localhost:8000/suivi-traitement/');
        $output->writeln('   - http://localhost:8000/suivi-traitement/mes-suivis');
        $output->writeln('   - http://localhost:8000/suivi-traitement/a-valider');

        return Command::SUCCESS;
    }
    
    private function cleanupOldData(OutputInterface $output): void
    {
        $output->writeln('🗑️ Nettoyage des anciennes données...');
        
        // Supprimer les suivis
        $suivis = $this->entityManager->getRepository(SuiviTraitement::class)->findAll();
        foreach ($suivis as $suivi) {
            $this->entityManager->remove($suivi);
        }
        
        // Supprimer les traitements
        $traitements = $this->entityManager->getRepository(Traitement::class)->findAll();
        foreach ($traitements as $traitement) {
            $this->entityManager->remove($traitement);
        }
        
        $this->entityManager->flush();
        $output->writeln('✅ Anciennes données supprimées');
    }
    
    private function createTestUsers(OutputInterface $output): array
    {
        $output->writeln('👥 Création des utilisateurs de test...');
        
        $users = [];
        
        // Étudiant
        $etudiant = new User();
        $etudiant->setNom('Étudiant');
        $etudiant->setPrenom('Test');
        $etudiant->setEmail('etudiant@test.com');
        $etudiant->setPassword(password_hash('test123', PASSWORD_DEFAULT));
        $etudiant->setStatut('actif');
        $etudiant->setRole(\App\Entity\Enum\RoleType::ETUDIANT);
        $etudiant->setCreatedAt(new \DateTime());
        $etudiant->setIsActive(true);
        $etudiant->setIsVerified(true);
        
        $this->entityManager->persist($etudiant);
        $users['etudiant'] = $etudiant;
        
        // Psychologue
        $psychologue = new User();
        $psychologue->setNom('Psychologue');
        $psychologue->setPrenom('Test');
        $psychologue->setEmail('psy@test.com');
        $psychologue->setPassword(password_hash('test123', PASSWORD_DEFAULT));
        $psychologue->setStatut('actif');
        $psychologue->setRole(\App\Entity\Enum\RoleType::PSYCHOLOGUE);
        $psychologue->setCreatedAt(new \DateTime());
        $psychologue->setIsActive(true);
        $psychologue->setIsVerified(true);
        
        $this->entityManager->persist($psychologue);
        $users['psychologue'] = $psychologue;
        
        $this->entityManager->flush();
        $output->writeln('✅ Utilisateurs créés');
        
        return $users;
    }
    
    private function createTestTraitements(array $users, OutputInterface $output): array
    {
        $output->writeln('🏥 Création des traitements de test...');
        
        $traitements = [];
        
        // Traitement 1
        $traitement1 = new Traitement();
        $traitement1->setTitre('Thérapie Comportementale');
        $traitement1->setDescription('Traitement basé sur la thérapie comportementale et cognitive');
        $traitement1->setDateDebut(new \DateTime('-1 month'));
        $traitement1->setDateFin(new \DateTime('+2 months'));
        $traitement1->setFrequence('hebdomadaire');
        $traitement1->setStatut(\App\Entity\Enum\StatutTraitement::EN_COURS);
        $traitement1->setCategorie(\App\Entity\Enum\CategorieTraitement::COMPORTEMENTAL);
        $traitement1->setPriorite(\App\Entity\Enum\PrioriteTraitement::MOYENNE);
        $traitement1->setEtudiant($users['etudiant']);
        $traitement1->setPsychologue($users['psychologue']);
        $traitement1->setCreatedAt(new \DateTime());
        $traitement1->setUpdatedAt(new \DateTime());
        
        $this->entityManager->persist($traitement1);
        $traitements[] = $traitement1;
        
        // Traitement 2
        $traitement2 = new Traitement();
        $traitement2->setTitre('Thérapie Cognitive');
        $traitement2->setDescription('Traitement basé sur la restructuration cognitive');
        $traitement2->setDateDebut(new \DateTime('-2 weeks'));
        $traitement2->setDateFin(new \DateTime('+1 month'));
        $traitement2->setFrequence('bi-hebdomadaire');
        $traitement2->setStatut(\App\Entity\Enum\StatutTraitement::EN_COURS);
        $traitement2->setCategorie(\App\Entity\Enum\CategorieTraitement::COGNITIF);
        $traitement2->setPriorite(\App\Entity\Enum\PrioriteTraitement::HAUTE);
        $traitement2->setEtudiant($users['etudiant']);
        $traitement2->setPsychologue($users['psychologue']);
        $traitement2->setCreatedAt(new \DateTime());
        $traitement2->setUpdatedAt(new \DateTime());
        
        $this->entityManager->persist($traitement2);
        $traitements[] = $traitement2;
        
        $this->entityManager->flush();
        $output->writeln('✅ Traitements créés');
        
        return $traitements;
    }
    
    private function createTestSuivis(array $traitements, OutputInterface $output): void
    {
        $output->writeln('📅 Création des suivis de test...');
        
        foreach ($traitements as $traitement) {
            // Suivi 1 - Effectué et validé
            $suivi1 = new SuiviTraitement();
            $suivi1->setTraitement($traitement);
            $suivi1->setDateSuivi(new \DateTime('-2 days'));
            $suivi1->setDateSaisie(new \DateTime('-2 days'));
            $suivi1->setObservations('J\'ai bien suivi les exercices aujourd\'hui. Je me sens mieux.');
            $suivi1->setObservationsPsy('Excellent travail. Le patient montre une bonne progression.');
            $suivi1->setEvaluation(8);
            $suivi1->setRessenti(Ressenti::BIEN);
            $suivi1->setSaisiPar(SaisiPar::ETUDIANT);
            $suivi1->setEffectue(true);
            $suivi1->setValide(true);
            $suivi1->setHeureEffective(new \DateTime('-2 days 14:30'));
            
            $this->entityManager->persist($suivi1);
            
            // Suivi 2 - Effectué mais en attente de validation
            $suivi2 = new SuiviTraitement();
            $suivi2->setTraitement($traitement);
            $suivi2->setDateSuivi(new \DateTime('-1 day'));
            $suivi2->setDateSaisie(new \DateTime('-1 day'));
            $suivi2->setObservations('J\'ai trouvé ça un peu difficile aujourd\'hui, mais j\'ai fait de mon mieux.');
            $suivi2->setObservationsPsy('');
            $suivi2->setEvaluation(6);
            $suivi2->setRessenti(Ressenti::DIFFICILE);
            $suivi2->setSaisiPar(SaisiPar::ETUDIANT);
            $suivi2->setEffectue(true);
            $suivi2->setValide(false);
            $suivi2->setHeureEffective(new \DateTime('-1 day 10:15'));
            
            $this->entityManager->persist($suivi2);
            
            // Suivi 3 - À faire aujourd'hui
            $suivi3 = new SuiviTraitement();
            $suivi3->setTraitement($traitement);
            $suivi3->setDateSuivi(new \DateTime());
            $suivi3->setDateSaisie(new \DateTime());
            $suivi3->setObservations('');
            $suivi3->setObservationsPsy('');
            $suivi3->setEvaluation(0);
            $suivi3->setRessenti(Ressenti::NEUTRE);
            $suivi3->setSaisiPar(SaisiPar::SYSTEME);
            $suivi3->setEffectue(false);
            $suivi3->setValide(false);
            
            $this->entityManager->persist($suivi3);
            
            // Suivi 4 - En retard
            $suivi4 = new SuiviTraitement();
            $suivi4->setTraitement($traitement);
            $suivi4->setDateSuivi(new \DateTime('-3 days'));
            $suivi4->setDateSaisie(new \DateTime('-3 days'));
            $suivi4->setObservations('J\'ai oublié de faire le suivi...');
            $suivi4->setObservationsPsy('');
            $suivi4->setEvaluation(0);
            $suivi4->setRessenti(Ressenti::DIFFICILE);
            $suivi4->setSaisiPar(SaisiPar::ETUDIANT);
            $suivi4->setEffectue(false);
            $suivi4->setValide(false);
            
            $this->entityManager->persist($suivi4);
        }
        
        $this->entityManager->flush();
        $output->writeln('✅ Suivis créés');
    }
}
