<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La succession familiale (doc 13, lot 11.5).
 *
 * Le nom de famille ne change pas, le **chef** change : la lignée porte le nom,
 * la génération porte quelqu'un. Le trait de la génération se renouvelle à
 * chaque succession, quand la renommée, les contacts et la ville persistent.
 *
 * `graine_des_heritiers` garde de quoi **redéduire** les héritiers proposés
 * plutôt que de les persister : deux visites du même écran en montrent les
 * mêmes, sans qu'aucune table ne les porte.
 *
 * Les parties en cours démarrent à la première génération, sans chef nommé —
 * ce qu'elles étaient déjà.
 */
final class Version20260903105337 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le chef de famille, sa génération et son trait de lignée.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE family ADD chef_de_famille VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE family ADD generation INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE family ALTER generation DROP DEFAULT');
        $this->addSql('ALTER TABLE family ADD trait_de_lignee VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE family ADD graine_des_heritiers INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE family ALTER graine_des_heritiers DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE family DROP chef_de_famille');
        $this->addSql('ALTER TABLE family DROP generation');
        $this->addSql('ALTER TABLE family DROP trait_de_lignee');
        $this->addSql('ALTER TABLE family DROP graine_des_heritiers');
    }
}
