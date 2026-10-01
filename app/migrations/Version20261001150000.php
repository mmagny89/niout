<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les deux exercices d'écriture — les sons, la lecture d'un cartouche — ne
 * rapportent qu'une fois par quinzaine : on retient la dernière quinzaine
 * récompensée, rien d'autre.
 */
final class Version20261001150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exercices des scribes : dernière quinzaine récompensée, par exercice';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD exercice_des_sons_au_cycle INT DEFAULT NULL');
        $this->addSql('ALTER TABLE city ADD lecture_de_cartouche_au_cycle INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP exercice_des_sons_au_cycle');
        $this->addSql('ALTER TABLE city DROP lecture_de_cartouche_au_cycle');
    }
}
