<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un seul rôle privilégié : `ROLE_ADMIN` absorbe `ROLE_DIVIN`.
 *
 * Les deux rôles désignaient la même poignée de comptes et se donnaient de la
 * même façon, en console : deux commandes à jouer et deux barrières à tenir à
 * jour pour une seule population. `ROLE_ADMIN` ouvre désormais l'administration
 * des comptes **et** le mode divin.
 *
 * **Les comptes existants sont convertis, jamais dégradés** : qui portait
 * `ROLE_DIVIN` reçoit `ROLE_ADMIN`. Sans cette conversion, le mode d'essai
 * disparaîtrait silencieusement des comptes qui l'avaient — le rôle resterait
 * en base, ne serait plus lu par rien, et aucune erreur ne le signalerait.
 *
 * La bascule inverse rend `ROLE_DIVIN` aux comptes convertis, mais **ne peut
 * pas distinguer** ceux qui n'avaient que l'administration de ceux qui avaient
 * le mode divin : la fusion perd cette information, et c'est le prix qu'elle a
 * été acceptée pour ce qu'elle vaut.
 */
final class Version20260911120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusionne ROLE_DIVIN dans ROLE_ADMIN sur les comptes existants.';
    }

    public function up(Schema $schema): void
    {
        // Les rôles sont un tableau JSON : la substitution porte sur le texte,
        // et `DISTINCT` du côté PHP n'existe pas ici — d'où le retrait
        // préalable d'un éventuel ROLE_ADMIN déjà présent, qui produirait
        // sinon un doublon dans le tableau.
        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET roles = (
                SELECT COALESCE(jsonb_agg(DISTINCT valeur), '[]'::jsonb)::json
                FROM jsonb_array_elements_text(roles::jsonb) AS elements(element),
                     LATERAL (SELECT CASE WHEN element = 'ROLE_DIVIN' THEN 'ROLE_ADMIN' ELSE element END) AS remplace(valeur)
            )
            WHERE roles::text LIKE '%ROLE_DIVIN%'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET roles = (
                SELECT COALESCE(jsonb_agg(DISTINCT valeur), '[]'::jsonb)::json
                FROM jsonb_array_elements_text(roles::jsonb) AS elements(element),
                     LATERAL (SELECT CASE WHEN element = 'ROLE_ADMIN' THEN 'ROLE_DIVIN' ELSE element END) AS remplace(valeur)
            )
            WHERE roles::text LIKE '%ROLE_ADMIN%'
            SQL);
    }
}
