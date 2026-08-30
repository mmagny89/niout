<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830103639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marque les parties d\'essai, où le mode divin lève les plafonds.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Défaut posé puis retiré : sans lui, l'ajout d'une colonne NOT NULL
        // échoue sur toute ville déjà enregistrée.
        $this->addSql('ALTER TABLE city ADD mode_divin BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE city ALTER mode_divin DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city DROP mode_divin');
    }
}
