<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830113742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un ordre de fabrication par bâtiment : l\'Atelier et la Forge travaillent de front.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_ordre_par_ville');
        // Les ordres déjà passés sont tous d'Atelier : la Forge n'en prenait
        // pas encore. Le défaut sert à les renseigner, puis se retire.
        $this->addSql("ALTER TABLE ordre_de_fabrication ADD batiment VARCHAR(255) NOT NULL DEFAULT 'atelier'");
        $this->addSql('ALTER TABLE ordre_de_fabrication ALTER batiment DROP DEFAULT');
        $this->addSql('CREATE INDEX IDX_9818FC3EA73F0036 ON ordre_de_fabrication (ville_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDRE_PAR_BATIMENT ON ordre_de_fabrication (ville_id, batiment)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_9818FC3EA73F0036');
        $this->addSql('DROP INDEX UNIQ_ORDRE_PAR_BATIMENT');
        $this->addSql('ALTER TABLE ordre_de_fabrication DROP batiment');
        $this->addSql('CREATE UNIQUE INDEX uniq_ordre_par_ville ON ordre_de_fabrication (ville_id)');
    }
}
