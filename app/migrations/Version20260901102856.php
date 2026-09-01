<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les deux mesures cumulatives d'une mission (lot 8.1).
 *
 * Les parties existantes repartent de zéro : ce qu'elles ont déjà échangé ou
 * rapporté n'a jamais été compté, et l'inventer serait pire que de l'ignorer.
 */
final class Version20260901102856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Objectifs : valeur échangée et ressources rapportées, cumulées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD ressources_rapportees JSON NOT NULL DEFAULT \'{}\'');
        $this->addSql('ALTER TABLE city ADD valeur_echangee INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE city ALTER ressources_rapportees DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER valeur_echangee DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP ressources_rapportees');
        $this->addSql('ALTER TABLE city DROP valeur_echangee');
    }
}
