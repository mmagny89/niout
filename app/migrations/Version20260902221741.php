<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les chars loués pour une expédition (doc 03, doc 01, lot 10.6).
 *
 * Ils vivent sur l'expédition et nulle part ailleurs : le Charrier n'a pas
 * d'entité, ne rejoint jamais l'effectif de la ville et disparaît avec la
 * sortie qui l'a demandé. C'est la traduction en base de la distinction
 * historique entre les Medjaÿ, corps de sécurité intérieure, et la *mesha*,
 * l'armée professionnelle de l'État.
 *
 * `DEFAULT 0` le temps de l'ajout puis retiré, comme partout ailleurs.
 */
final class Version20260902221741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les chars réquisitionnés pour une expédition.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expedition ADD charriers INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE expedition ALTER charriers DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expedition DROP charriers');
    }
}
