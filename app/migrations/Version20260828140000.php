<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'or cesse d'être la monnaie : le deben la devient (lot 4.0).
 *
 * L'Égypte pharaonique n'a pas de monnaie frappée — elle n'apparaît que sous
 * domination perse, puis chez les Ptolémées. Le Nouvel Empire compte en deben,
 * unité pondérale d'environ 91 g attestée par les ostraca de Deir el-Médineh.
 *
 * Aucun changement de schéma : le stock est une table `ressource → quantité`
 * depuis le lot 3.1, et l'énumération est stockée en chaîne. Seules les
 * **données** bougent.
 *
 * **Seul le stock est converti, jamais les gisements.** Une ligne de stock `or`
 * était de la monnaie ; un gisement `or` est une mine — celles du désert
 * oriental et de Nubie, que le doc 08 décrit et que la mission 2 porte. Les
 * confondre transformerait les mines de Nubie en carrières de monnaie, ce que
 * cette migration existe précisément pour défaire.
 */
final class Version20260828140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le stock en or devient un stock en deben ; les gisements d\'or restent des mines.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE stock_de_ressource SET ressource = 'deben' WHERE ressource = 'or'");
    }

    /**
     * Le retour en arrière ne peut pas être parfait : une ville qui aurait à la
     * fois des deben et de l'or extrait porterait deux lignes que ce `down`
     * fondrait en une, ce que la contrainte d'unicité (ville, ressource)
     * refuserait. On ne convertit donc que les villes qui n'ont pas déjà d'or
     * en stock — les autres gardent leurs deben, qu'un retour en arrière
     * rendrait de toute façon illisibles.
     */
    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE stock_de_ressource s SET ressource = 'or'
             WHERE s.ressource = 'deben'
               AND NOT EXISTS (
                   SELECT 1 FROM stock_de_ressource autre
                   WHERE autre.ville_id = s.ville_id AND autre.ressource = 'or'
               )",
        );
    }
}
