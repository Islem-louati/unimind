<?php

namespace App\Command;

use App\Service\EmailManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:mailjet-diagnostic')]
class MailjetDiagnosticCommand extends Command
{
    public function __construct(
        private EmailManager $emailManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de test')
            ->setDescription('Diagnostic complet de la configuration Mailjet')
            ->setHelp('Cette commande teste la configuration Mailjet étape par étape');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('🔍 DIAGNOSTIC COMPLET MAILJET');
        $io->writeln('Date: ' . date('Y-m-d H:i:s'));
        $io->writeln('Email test: ' . $email);
        $io->writeln('');

        // ÉTAPE 1: Vérification de la configuration
        $io->section('1. VÉRIFICATION DE LA CONFIGURATION');

        $dsn = $_ENV['MAILER_DSN'] ?? 'Non défini';
        $io->writeln('DSN configuré: ' . $dsn);

        if (strpos($dsn, 'mailjet://') === 0) {
            $io->success('✓ Configuration Mailjet détectée');
            
            // Extraire les clés pour vérification
            preg_match('/mailjet:\/\/([^:]+):([^@]+)@/', $dsn, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $apiKey = substr($matches[1], 0, 8) . '...';
                $secretKey = substr($matches[2], 0, 8) . '...';
                $io->writeln("  API Key: $apiKey");
                $io->writeln("  Secret Key: $secretKey");
            }
        } else {
            $io->error('✗ Configuration Mailjet non trouvée');
            $io->writeln('  Vérifiez votre fichier .env');
        }

        // ÉTAPE 2: Vérification de l'expéditeur
        $io->section('2. VÉRIFICATION DE L\'EXPÉDITEUR');
        
        $mailerFrom = $_ENV['MAILER_FROM'] ?? 'Non défini';
        $mailerFromName = $_ENV['MAILER_FROM_NAME'] ?? 'Non défini';
        
        $io->writeln("Expéditeur: $mailerFromName <$mailerFrom>");
        $io->writeln("\n⚠️  IMPORTANT: Cet email doit être vérifié dans Mailjet !");
        $io->writeln("   Allez sur https://app.mailjet.com → Account Settings → Sender addresses");
        $io->writeln("   Vérifiez que '$mailerFrom' est dans la liste et a le statut 'Active'");

        // ÉTAPE 3: Test de connexion SMTP (CORRIGÉ)
        $io->section('3. TEST DE CONNEXION SMTP');

        try {
            // CORRECTION: Utilisation correcte de Transport::fromDsn
            $transport = Transport::fromDsn($dsn);
            $io->writeln("✓ Transport créé avec succès");
            
        } catch (\Exception $e) {
            $io->error('✗ Échec de création du transport');
            $io->writeln('  Erreur: ' . $e->getMessage());
        }

        // ÉTAPE 4: Test d'envoi d'email
        $io->section('4. TEST D\'ENVOI D\'EMAIL');

        $io->writeln("Envoi à: $email");
        $io->writeln("Envoi en cours...");

        $result = $this->emailManager->testDirectEmail($email);

        if ($result['success']) {
            $io->success('✓ ' . $result['message']);
        } else {
            $io->error('✗ ' . $result['message']);
            if (isset($result['exception'])) {
                $io->writeln('  Type: ' . $result['exception']['type']);
                $io->writeln('  Message: ' . $result['exception']['message']);
                $io->writeln('  Code: ' . $result['exception']['code']);
            }
        }

        // ÉTAPE 5: Recommandations
        $io->section('5. RECOMMANDATIONS');

        $io->writeln('📋 Actions à effectuer:');
        $io->writeln('');

        $steps = [
            '1️⃣  Vérifier l\'expéditeur dans Mailjet:',
            '   - Allez sur https://app.mailjet.com',
            '   - Menu: Account Settings → Sender addresses',
            '   - Vérifiez que ' . $_ENV['MAILER_FROM'] . ' est "Active"',
            '   - Sinon, ajoutez-le et vérifiez l\'email de confirmation',
            '',
            '2️⃣  Vérifier les clés API:',
            '   - Allez dans Account Settings → API Keys',
            '   - Vérifiez que les clés ont les permissions d\'envoi',
            '   - Régénérez si nécessaire',
            '',
            '3️⃣  Vérifier les logs Symfony:',
            '   - Fichier: var/log/dev.log',
            '   - Commande: tail -f var/log/dev.log',
            '   - (Windows PowerShell: Get-Content var/log/dev.log -Wait)',
            '',
            '4️⃣  Vérifier les spams Gmail:',
            '   - Allez dans https://mail.google.com',
            '   - Vérifiez le dossier SPAM',
            '   - Ajoutez l\'expéditeur à vos contacts',
            '',
            '5️⃣  Test alternatif avec curl:',
            '   ```bash',
            '   curl -X POST \\',
            '     https://api.mailjet.com/v3.1/send \\',
            '     -H "Content-Type: application/json" \\',
            '     -u "' . $_ENV['MAILER_FROM'] . ':' . ($_ENV['MAILER_DSN'] ? substr(explode(':', explode('@', $_ENV['MAILER_DSN'])[0])[2] ?? '', 0, 8) . '...' : 'VOTRE_API_SECRET') . '" \\',
            '     -d \'{"Messages":[{"From":{"Email":"' . $_ENV['MAILER_FROM'] . '","Name":"UniMind"},"To":[{"Email":"' . $email . '","Name":"Test"}],"Subject":"Test API","TextPart":"Test"}]}\'',
            '   ```'
        ];

        foreach ($steps as $step) {
            $io->writeln($step);
        }

        // Résumé final
        $io->section('RÉSUMÉ');

        if ($result['success']) {
            $io->success('✅ Le test est réussi mais l\'email n\'arrive pas.');
            $io->writeln('   Causes possibles:');
            $io->writeln('   - L\'email est dans les SPAM (vérifiez Gmail)');
            $io->writeln('   - L\'expéditeur n\'est pas vérifié (point 1 ci-dessus)');
            $io->writeln('   - Délai de livraison (parfois 5-10 minutes)');
        } else {
            $io->error('❌ Le test a échoué.');
            $io->writeln('   Causes possibles:');
            $io->writeln('   - Clés API invalides');
            $io->writeln('   - Expéditeur non vérifié');
            $io->writeln('   - Problème de connexion réseau');
            $io->writeln('   - Quota dépassé (100 emails/jour)');
        }

        $io->writeln('');
        $io->writeln('🔗 Lien direct Mailjet: https://app.mailjet.com/transactional/messages');

        return Command::SUCCESS;
    }
}