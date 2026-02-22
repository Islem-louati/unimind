<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221233620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename Evenement.type value from webinaire to formation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE evenement SET type = 'formation' WHERE type = 'webinaire'");

    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE evenement SET type = 'webinaire' WHERE type = 'formation'");

    }
}
