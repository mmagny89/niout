<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le prix fait aux habitants, et le salaire des bras.
 *
 * Deux réglages que le joueur tenait jusqu'ici pour acquis : la ville vendait
 * au cours de base et payait un deben la quinzaine, sans qu'on puisse y
 * toucher. Chacun a sa contrepartie — un prix abusif et un salaire de misère
 * mécontentent la ville, un salaire généreux l'apaise —, sans quoi il
 * n'existerait qu'une seule bonne valeur pour chacun.
 *
 * **Les valeurs par défaut reprennent exactement le comportement d'avant** :
 * cent pour cent du cours, un deben de salaire. Aucune partie en cours ne
 * change de comportement du fait de cette migration.
 */
final class Version20260903174302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prix fait aux habitants et salaire des travailleurs, réglables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD marge_du_marche INT DEFAULT 100 NOT NULL');
        $this->addSql('ALTER TABLE city ADD salaire_de_base INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP marge_du_marche');
        $this->addSql('ALTER TABLE city DROP salaire_de_base');
    }
}
