<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Du bois local pour les cartes déjà générées.
 *
 * Le doc 01 révisé fait du bois local un matériau que **tout** bâtiment
 * réclame. Les cartes créées avant lui n'en portent aucun : leurs parties
 * seraient imbâtissables de bout en bout, ce que la génération garantit
 * désormais de ne plus produire (`GenerateurDeCarte::garantirDuBoisLocal()`).
 *
 * C'est une **réparation, pas une invention** : ces cartes auraient reçu ce
 * bosquet si elles avaient été générées aujourd'hui. Le gisement se pose sur
 * une terre fertile — les cartes anciennes n'ont pas de terre broussailleuse,
 * ce terrain n'existait pas —, hors de la case de la ville et sur une case qui
 * ne porte pas déjà deux gisements (`Zone::GISEMENTS_MAX`).
 */
final class Version20260829210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pose un gisement de bois local sur les cartes générées avant qu\'il n\'existe.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO gisement (ressource, quantite_restante, exploitee, zone_id)
            SELECT 'bois_local', 200, false, elue.id
            FROM (
                SELECT DISTINCT ON (z.ville_id) z.id, z.ville_id
                FROM zone z
                WHERE z.terrain = 'fertile'
                  AND z.porte_la_ville = false
                  AND (SELECT count(*) FROM gisement g WHERE g.zone_id = z.id) < 2
                  AND NOT EXISTS (
                      SELECT 1 FROM gisement g
                      JOIN zone zz ON zz.id = g.zone_id
                      WHERE zz.ville_id = z.ville_id AND g.ressource = 'bois_local'
                  )
                ORDER BY z.ville_id, z.id
            ) AS elue
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM gisement WHERE ressource = 'bois_local'");
    }
}
