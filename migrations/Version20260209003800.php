<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209003800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feedback fields to participation (note, commentaire, date)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation ADD note_satisfaction SMALLINT DEFAULT NULL, ADD feedback_commentaire LONGTEXT DEFAULT NULL, ADD feedback_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP note_satisfaction, DROP feedback_commentaire, DROP feedback_at');
    }
}
