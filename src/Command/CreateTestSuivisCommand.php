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

#[AsCommand(name: 'app:create-test-suivis')]
class CreateTestSuivisCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔧 Création de suivis de test...');
        
        // Récupérer les traitements existants
        $traitements = $this->entityManager->getRepository(Traitement::class)->findAll();
        
        if (empty($traitements)) {
            $output->writeln('❌ Aucun traitement trouvé. Créez d\'abord des traitements avec la commande app:create-test-users');
            return Command::FAILURE;
        }

        // Supprimer les anciens suivis de test
        $oldSuivis = $this->entityManager->getRepository(SuiviTraitement::class)->findAll();
        foreach ($oldSuivis as $suivi) {
            $this->entityManager->remove($suivi);
        }
        $this->entityManager->flush();

        $suivis = [];
        
        // Créer des suivis pour chaque traitement
        foreach ($traitements as $traitement) {
            // Suivi 1 - Effectué et validé
            $suivi1 = new SuiviTraitement();
            $suivi1->setTraitement($traitement);
            $suivi1->setDateSuivi(new \DateTime('-2 days'));
            $suivi1->setDateSaisie(new \DateTime('-2 days'));
            $suivi1->setObservations('Suivi effectué avec succès. Le patient a bien suivi les exercices.');
            $suivi1->setObservationsPsy('Le patient montre une bonne progression. Continuez avec la même approche.');
            $suivi1->setEvaluation(8);
            $suivi1->setRessenti(Ressenti::BIEN);
            $suivi1->setSaisiPar(SaisiPar::ETUDIANT);
            $suivi1->setEffectue(true);
            $suivi1->setValide(true);
            $suivi1->setHeureEffective(new \DateTime('-2 days 14:30'));
            
            $this->entityManager->persist($suivi1);
            $suivis[] = $suivi1;

            // Suivi 2 - Effectué mais en attente de validation
            $suivi2 = new SuiviTraitement();
            $suivi2->setTraitement($traitement);
            $suivi2->setDateSuivi(new \DateTime('-1 day'));
            $suivi2->setDateSaisie(new \DateTime('-1 day'));
            $suivi2->setObservations('J\'ai trouvé les exercices un peu difficiles aujourd\'hui, mais j\'ai fait de mon mieux.');
            $suivi2->setObservationsPsy('');
            $suivi2->setEvaluation(6);
            $suivi2->setRessenti(Ressenti::DIFFICILE);
            $suivi2->setSaisiPar(SaisiPar::ETUDIANT);
            $suivi2->setEffectue(true);
            $suivi2->setValide(false);
            $suivi2->setHeureEffective(new \DateTime('-1 day 10:15'));
            
            $this->entityManager->persist($suivi2);
            $suivis[] = $suivi2;

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
            $suivis[] = $suivi3;

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
            $suivis[] = $suivi4;
        }

        $this->entityManager->flush();

        $output->writeln('✅ ' . count($suivis) . ' suivis de test créés avec succès !');
        $output->writeln('');
        $output->writeln('📊 Répartition créée :');
        $output->writeln('   - Effectués et validés : ' . count($traitements));
        $output->writeln('   - Effectués en attente : ' . count($traitements));
        $output->writeln('   - À faire aujourd\'hui : ' . count($traitements));
        $output->writeln('   - En retard : ' . count($traitements));
        $output->writeln('');
        $output->writeln('🚀 Vous pouvez maintenant tester les routes suivantes :');
        $output->writeln('   - http://localhost:8000/suivi-traitement/');
        $output->writeln('   - http://localhost:8000/suivi-traitement/mes-suivis');
        $output->writeln('   - http://localhost:8000/suivi-traitement/a-valider');
        $output->writeln('   - http://localhost:8000/api/suivis/');

        return Command::SUCCESS;
    }
}
