<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La chronique d'une personne : ses faits, bornés, en JSON avec le défaut `'[]'`
 * (`docs/plan-pnj-palier-b.md` § 8).
 */
final class Version20261116200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Histoire des personnes du monde';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE npc ADD histoire JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE npc DROP histoire');
    }
}
