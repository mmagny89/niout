<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828203602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Défaut posé puis retiré : sans lui, l'ajout d'une colonne NOT NULL
        // échoue sur toute partie déjà enregistrée.
        $this->addSql('ALTER TABLE game_save ADD quinzaines_de_mecontentement INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE game_save ALTER quinzaines_de_mecontentement DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_save DROP quinzaines_de_mecontentement');
    }
}
