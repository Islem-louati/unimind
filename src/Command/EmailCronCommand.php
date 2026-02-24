<?php

namespace App\Command;

use App\Service\EmailSchedulerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:email-cron',
    description: 'Exécute les tâches automatiques d\'envoi d\'emails'
)]
class EmailCronCommand extends Command
{
    private EmailSchedulerService $emailScheduler;

    public function __construct(EmailSchedulerService $emailScheduler)
    {
        $this->emailScheduler = $emailScheduler;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Début de l\'exécution des tâches email automatiques...</info>');

        try {
            // 1. Envoyer les rappels de suivi automatiques
            $output->writeln('<comment>Envoi des rappels de suivi automatiques...</comment>');
            $resultatsRappels = $this->emailScheduler->sendAutomaticReminders();
            
            $output->writeln(sprintf(
                '<info>Rappels envoyés : %d emails, %d erreurs</info>',
                $resultatsRappels['emails_envoyes'],
                $resultatsRappels['erreurs']
            ));

            // 2. Envoyer les rapports hebdomadaires
            $output->writeln('<comment>Envoi des rapports hebdomadaires...</comment>');
            $resultatsRapports = $this->emailScheduler->sendWeeklyReports();
            
            $output->writeln(sprintf(
                '<info>Rapports envoyés : %d rapports, %d erreurs</info>',
                $resultatsRapports['rapports_envoyes'],
                $resultatsRapports['erreurs']
            ));

            $output->writeln('<success>Tâches email automatiques terminées avec succès !</success>');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Erreur lors de l\'exécution des tâches email : %s</error>',
                $e->getMessage()
            ));
            return Command::FAILURE;
        }
    }
}
