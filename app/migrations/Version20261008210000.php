<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une consigne — et un ordre — par travailleur : les ordres et les consignes de fabrication
 * se rangent par poste. Les lignes existantes prennent le poste 1, qui était leur seule place.
 */
final class Version20261008210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fabrication : un poste par travailleur, pour les ordres et les consignes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ORDRE_PAR_BATIMENT');
        $this->addSql('ALTER TABLE ordre_de_fabrication ADD poste INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDRE_PAR_POSTE ON ordre_de_fabrication (ville_id, batiment, poste)');

        $this->addSql('DROP INDEX UNIQ_CONSIGNE_PAR_BATIMENT');
        $this->addSql('ALTER TABLE consigne_de_fabrication ADD poste INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CONSIGNE_PAR_POSTE ON consigne_de_fabrication (ville_id, batiment, poste)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ORDRE_PAR_POSTE');
        $this->addSql('ALTER TABLE ordre_de_fabrication DROP poste');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDRE_PAR_BATIMENT ON ordre_de_fabrication (ville_id, batiment)');

        $this->addSql('DROP INDEX UNIQ_CONSIGNE_PAR_POSTE');
        $this->addSql('ALTER TABLE consigne_de_fabrication DROP poste');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CONSIGNE_PAR_BATIMENT ON consigne_de_fabrication (ville_id, batiment)');
    }
}
