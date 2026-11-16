<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La graine du monde : de quoi déduire, et rejouer, la conjoncture des marchés
 * (`docs/plan-pnj-palier-a.md` § 4.1).
 *
 * Le défaut `0` est conservé. Les parties existantes reçoivent une graine dérivée de leur
 * identifiant — stable, et propre à chacune — plutôt que zéro, qui ferait un monde commun.
 */
final class Version20261116160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Graine du monde sur la partie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save ADD graine INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE game_save SET graine = 1 + ((id::bigint * 2654435761) % 2147483646)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP graine');
    }
}
