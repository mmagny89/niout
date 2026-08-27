<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 3.5 — exploitation des gisements, champs et cycle agricole.
 *
 * Porte aussi la disparition des ressources génériques « bois » et « pierre » :
 * ce sont désormais des familles de matériaux, pas des lignes de stock (voir
 * App\Game\FamilleDeMateriau). Les stocks existants sont donc convertis vers un
 * matériau nommé — roseaux et argile, ceux du Delta, seule région jouable à ce
 * stade de la campagne.
 */
final class Version20260827203212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gisements exploitables, champs, crue annuelle ; bois et pierre deviennent des familles.';
    }

    public function up(Schema $schema): void
    {
        // Les colonnes arrivent avec une valeur par défaut, sans quoi les
        // parties déjà en cours ne pourraient pas satisfaire le NOT NULL.
        $this->addSql("ALTER TABLE game_save ADD crue VARCHAR(255) NOT NULL DEFAULT 'normale'");
        $this->addSql('ALTER TABLE zone ADD exploitee BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE zone ADD culture VARCHAR(255) DEFAULT NULL');

        // La valeur par défaut a joué son rôle sur l'existant ; le code fournit
        // désormais la sienne, et la laisser masquerait un oubli de mapping.
        $this->addSql('ALTER TABLE game_save ALTER crue DROP DEFAULT');
        $this->addSql('ALTER TABLE zone ALTER exploitee DROP DEFAULT');

        // Conversion des stocks génériques. La fusion est nécessaire : une
        // ville qui possédait déjà des roseaux violerait sinon la contrainte
        // d'unicité (ville_id, ressource).
        $this->convertir('bois', 'roseaux');
        $this->convertir('pierre', 'argile');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP crue');
        $this->addSql('ALTER TABLE zone DROP exploitee');
        $this->addSql('ALTER TABLE zone DROP culture');

        // La conversion des stocks n'est pas réversible : une fois les roseaux
        // fondus dans le stock existant, plus rien ne dit lesquels venaient du
        // « bois » générique.
    }

    /**
     * Verse une ressource générique dans la ressource nommée qui la remplace,
     * en additionnant les quantités là où les deux coexistent.
     */
    private function convertir(string $generique, string $nommee): void
    {
        $this->addSql(
            'UPDATE stock_de_ressource cible
             SET quantite = cible.quantite + source.quantite
             FROM stock_de_ressource source
             WHERE source.ressource = :generique
               AND cible.ressource = :nommee
               AND cible.ville_id = source.ville_id',
            ['generique' => $generique, 'nommee' => $nommee],
        );

        $this->addSql(
            'UPDATE stock_de_ressource
             SET ressource = :nommee
             WHERE ressource = :generique
               AND ville_id NOT IN (SELECT ville_id FROM stock_de_ressource WHERE ressource = :nommee)',
            ['generique' => $generique, 'nommee' => $nommee],
        );

        $this->addSql(
            'DELETE FROM stock_de_ressource WHERE ressource = :generique',
            ['generique' => $generique],
        );
    }
}
