<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les prénoms que peut porter un chef de famille (doc 13, lot 11.5).
 *
 * **Tous attestés, aucun inventé** — même exigence que pour les cartouches et
 * les stèles. Ils viennent des sources ordinaires du Nouvel Empire : les
 * registres du village de Deir el-Médineh, les tombes de particuliers de la
 * nécropole thébaine, les papyrus administratifs. Ce sont des noms de scribes,
 * d'artisans, de contremaîtres et de leurs épouses — **jamais de pharaons** :
 * la famille du joueur n'est pas royale, et le doc 09 pose déjà cette règle
 * pour `Family::NOM_PAR_DEFAUT`.
 *
 * **Hommes et femmes ensemble** : les Égyptiennes travaillaient, tenaient des
 * biens et disposaient d'une autonomie juridique inhabituelle pour l'époque
 * (doc 02). Rien ne justifierait qu'une lignée ne se transmette qu'aux fils.
 */
final readonly class PrenomEgyptien
{
    /**
     * Les gens ordinaires de Deir el-Médineh : contremaîtres, scribes et
     * artisans.
     *
     * @var list<string>
     */
    private const array ARTISANS = [
        'Sennedjem', 'Ipouy', 'Pached', 'Paneb', 'Kaha', 'Baki',
        'Anherkhâou', 'Penbouy', 'Nebnefer', 'Hori',
    ];

    /**
     * Hauts fonctionnaires et scribes de la nécropole thébaine. **Plusieurs
     * désignent des personnes identifiées** (un vizir, un architecte, un
     * maire) : le fonds des chefs et des Medjaÿ ne les emploie donc pas — un
     * chef de grenier ne doit pas laisser entendre qu'il *est* quelqu'un de
     * réel. Ils restent ici pour les héritiers, dont l'ordre ne doit pas bouger.
     *
     * @var list<string>
     */
    private const array FONCTIONNAIRES = [
        'Sennefer', 'Rekhmirê', 'Menna', 'Nebamon', 'Ineni', 'Ramose',
        'Ouserhat', 'Kenamon', 'Neferhotep', 'Djehoutymes',
    ];

    /**
     * Épouses, filles et maîtresses de maison, attestées aux mêmes sources et
     * souvent représentées à côté d'eux.
     *
     * @var list<string>
     */
    private const array FEMMES = [
        'Iyneferti', 'Meryt', 'Hatnefer', 'Henouttaouy', 'Baketamon',
        'Renenoutet', 'Ahmès', 'Nefertari', 'Taouseret', 'Moutemouia',
    ];

    /**
     * @return list<string>
     */
    public static function tous(): array
    {
        return [...self::ARTISANS, ...self::FONCTIONNAIRES, ...self::FEMMES];
    }

    /**
     * Les prénoms qu'un chef ou un Medjaÿ peut porter : ceux des gens
     * ordinaires, hommes et femmes. **Sans les hauts fonctionnaires**, que
     * `FONCTIONNAIRES` réserve aux héritiers.
     *
     * @return list<string>
     */
    public static function ordinaires(): array
    {
        return [...self::ARTISANS, ...self::FEMMES];
    }

    /**
     * Les prénoms d'hommes parmi les gens ordinaires — ceux que prennent les
     * Medjaÿ, qui sont des hommes d'armes (doc 03 : « un homme »).
     *
     * @return list<string>
     */
    public static function ordinairesMasculins(): array
    {
        return self::ARTISANS;
    }

    /**
     * Un prénom tiré d'après une graine — le même tirage rend toujours le même
     * nom. C'est ce qui permet de proposer des héritiers sans les persister :
     * seule la graine se garde.
     */
    public static function selonLaGraine(int $graine): string
    {
        $tous = self::tous();

        return $tous[abs($graine) % \count($tous)];
    }
}
