<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le profil d'une personne propre à son rôle (chef voyageur : sa candidature, sa destination). JSON, défaut `'{}'`
 * (`docs/plan-pnj-palier-b.md` § 5.2).
 */
final class Version20261116210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil des personnes du monde';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE npc ADD profil JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE npc DROP profil');
    }
}
