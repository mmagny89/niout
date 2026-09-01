<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le score d'une mission close (lot 8.2).
 *
 * Les parties existantes sont toutes en cours ou échouées : zéro est
 * exactement leur score.
 */
final class Version20260901105326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Missions : le score d\'une mission accomplie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save ADD score_de_mission INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE game_save ALTER score_de_mission DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP score_de_mission');
    }
}
