<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827152153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city ADD taille_grille INT NOT NULL');
        $this->addSql('ALTER TABLE city ADD stock_or INT NOT NULL');
        $this->addSql('ALTER TABLE city ADD bois INT NOT NULL');
        $this->addSql('ALTER TABLE city ADD pierre INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city DROP taille_grille');
        $this->addSql('ALTER TABLE city DROP stock_or');
        $this->addSql('ALTER TABLE city DROP bois');
        $this->addSql('ALTER TABLE city DROP pierre');
    }
}
