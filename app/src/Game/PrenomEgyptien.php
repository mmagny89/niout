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
     * @return list<string>
     */
    public static function tous(): array
    {
        return [
            // Contremaîtres, scribes et artisans de Deir el-Médineh.
            'Sennedjem', 'Ipouy', 'Pached', 'Paneb', 'Kaha', 'Baki',
            'Anherkhâou', 'Penbouy', 'Nebnefer', 'Hori',
            // Hauts fonctionnaires et scribes de la nécropole thébaine.
            'Sennefer', 'Rekhmirê', 'Menna', 'Nebamon', 'Ineni', 'Ramose',
            'Ouserhat', 'Kenamon', 'Neferhotep', 'Djehoutymes',
            // Épouses, filles et maîtresses de maison, attestées aux mêmes
            // sources et souvent représentées à côté d'eux.
            'Iyneferti', 'Meryt', 'Hatnefer', 'Henouttaouy', 'Baketamon',
            'Renenoutet', 'Ahmès', 'Nefertari', 'Taouseret', 'Moutemouia',
        ];
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
