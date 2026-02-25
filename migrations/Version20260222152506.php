<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222152506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favori (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, seance_id INT NOT NULL, INDEX IDX_EF85A2CCA76ED395 (user_id), INDEX IDX_EF85A2CCE3797A94 (seance_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CCA76ED395 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CCE3797A94 FOREIGN KEY (seance_id) REFERENCES seance_meditation (seance_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favori DROP FOREIGN KEY FK_EF85A2CCA76ED395');
        $this->addSql('ALTER TABLE favori DROP FOREIGN KEY FK_EF85A2CCE3797A94');
        $this->addSql('DROP TABLE favori');
    }
}
