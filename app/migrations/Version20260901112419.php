<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le legs du pharaon précédent (lot 8.6).
 *
 * Les parties existantes n'en ont reçu aucun : zéro est exactement leur cas.
 */
final class Version20260901112419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campagne : le legs reçu d\'une mission accomplie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save ADD legs_en_deben INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE game_save ALTER legs_en_deben DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP legs_en_deben');
    }
}
