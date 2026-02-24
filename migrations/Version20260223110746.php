<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223110746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suivi_traitement ADD document_name VARCHAR(255) DEFAULT NULL, ADD document_size INT DEFAULT NULL, ADD document_mime_type VARCHAR(255) DEFAULT NULL, ADD document_original_name VARCHAR(255) DEFAULT NULL, ADD document_updated_at DATETIME DEFAULT NULL, CHANGE observations observations TEXT NOT NULL, CHANGE observationsPsy observationsPsy TEXT NOT NULL');
        $this->addSql('ALTER TABLE traitement CHANGE psychologue_id psychologue_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suivi_traitement DROP document_name, DROP document_size, DROP document_mime_type, DROP document_original_name, DROP document_updated_at, CHANGE observations observations TEXT DEFAULT NULL, CHANGE observationsPsy observationsPsy TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE traitement CHANGE psychologue_id psychologue_id INT NOT NULL');
    }
}
