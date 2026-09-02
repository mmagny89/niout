<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que vaut l'arme d'un Medjaÿ (doc 03, doc 01, lot 10.3).
 *
 * Le doc 03 fait entrer `qualité_équipement` dans le score d'attaque, et le
 * doc 01 la chiffre : la Forge donne « +5 % de bonus de combat par niveau à
 * partir du niveau 3 ». C'est ce qui donne enfin aux **armes** une raison
 * d'exister autre que la vente.
 *
 * **Durable, jamais consommée** (arbitrage 10.0). Une arme se donne une fois et
 * reste avec l'homme : la Forge est un palier à franchir, pas un robinet à
 * tenir ouvert, et aucune chaîne de production ne décide du rythme militaire.
 *
 * **Un homme sans arme part quand même**, à qualité réduite. Rien ne bloque une
 * expédition : c'est ce qui évite qu'une carrière gardée reste imprenable parce
 * que le cuivre manque.
 *
 * **La qualité se fige à la remise de l'arme.** Monter la Forge n'améliore pas
 * rétroactivement les armes déjà données — il faut réarmer ses vétérans, ce qui
 * fait du niveau de Forge une décision et non un simple compteur.
 *
 * **L'Armurier n'entre pas ici.** Sa spécialité bonifie déjà la *production*
 * d'armes à la Forge, comme celles de l'Atelier bonifient leur recette : lui
 * donner en plus un effet sur la qualité lui en ferait deux, contre la
 * discipline du lot 6.3.
 */
final readonly class Equipement
{
    /**
     * Ce que vaut un homme armé d'une arme ordinaire, en centièmes. La
     * référence : tout se lit par rapport à elle.
     */
    public const int QUALITE_DE_REFERENCE = 100;

    /**
     * Ce que vaut un homme qui n'a que ses mains. **Valeur inventée** — aucun
     * document ne la chiffre. Sept dixièmes : de quoi rendre l'armement
     * nettement souhaitable sans jamais interdire une sortie.
     */
    public const int QUALITE_SANS_ARME = 70;

    /**
     * Le bonus par niveau de Forge, et le niveau à partir duquel il court
     * (doc 01 : « +5 % par niveau à partir du niveau 3 »). Une Forge de
     * niveau 6, son maximum, vaut donc +20 %.
     */
    public const int BONUS_PAR_NIVEAU_DE_FORGE = 5;
    public const int PREMIER_NIVEAU_QUI_COMPTE = 3;

    /**
     * Le niveau de Forge à partir duquel on sait faire des armes (doc 01 :
     * « niveau 1 outils basiques, niveau 2 armes basiques »).
     */
    public const int NIVEAU_QUI_ARME = 2;

    /**
     * Ce que vaut une arme forgée aujourd'hui dans cette ville.
     *
     * Sans Forge, l'arme vient d'ailleurs — achetée, importée — et vaut la
     * référence, sans plus : on ne bonifie que ce qu'on a fait soi-même.
     */
    public static function qualiteForgeePar(City $ville): int
    {
        $niveau = $ville->batimentDeType(TypeDeBatiment::Forge)?->getNiveau() ?? 0;

        if ($niveau < self::PREMIER_NIVEAU_QUI_COMPTE) {
            return self::QUALITE_DE_REFERENCE;
        }

        return self::QUALITE_DE_REFERENCE
            + self::BONUS_PAR_NIVEAU_DE_FORGE * ($niveau - self::PREMIER_NIVEAU_QUI_COMPTE + 1);
    }
}
