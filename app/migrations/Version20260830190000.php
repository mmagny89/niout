<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les cases restées sur un contenu qui n'existe plus.
 *
 * Le lot « terre classique » a retiré `ContenuDeZone::TerreNonCultivable` —
 * c'était un manque déguisé en contenu, remplacé par un vrai terrain — sans
 * convertir les lignes déjà en base. Doctrine ne sait pas hydrater une valeur
 * absente de l'enum : **toute partie portant une seule case de ce contenu
 * devenait illisible**, donc impossible à ouvrir comme à abandonner.
 *
 * Ces cases redeviennent ce qu'elles ont toujours été : des cases sans rien
 * dessus. Leur terrain, lui, n'a jamais bougé.
 *
 * La leçon vaut pour tout retrait futur d'un cas d'enum persisté : le retirer
 * du code ne le retire pas de la base, et le défaut ne se voit qu'à la
 * première lecture d'une vieille partie.
 */
final class Version20260830190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contenu de zone : les cases restées sur « terre_non_cultivable » redeviennent vides';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE zone SET contenu = 'rien' WHERE contenu = 'terre_non_cultivable'");
    }

    /**
     * Irréversible, et sans conséquence : le contenu retiré n'existe plus dans
     * le code, une case vide est exactement ce qu'il désignait.
     */
    public function down(Schema $schema): void
    {
    }
}
