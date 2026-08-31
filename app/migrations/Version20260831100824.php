<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les énigmes déjà tentées (lot 7.2).
 *
 * Justes ou fausses : une seule tentative par énigme, donc une seule liste.
 * Les villes déjà en base n'en ont tenté aucune.
 */
final class Version20260831100824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Énigmes : celles qu\'on a déjà tentées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD enigmes_tentees JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE city ALTER enigmes_tentees DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP enigmes_tentees');
    }
}
