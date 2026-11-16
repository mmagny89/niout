<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Les silhouettes de passage (lot I2 du plan « dialogues-indices »).
 *
 * Un voyageur à l'Auberge, un prêtre au Temple : **la voix de celui qui pose une énigme**, là où le
 * doc 10 veut qu'on l'entende — *c'est le lieu qui décide, jamais l'écran*. Ce n'est pas une personne de
 * la ville : pas de nom, pas de mémoire, jamais dans la photographie ni dans le film d'une quinzaine.
 *
 * **Elle ne dit que ce qui est vrai maintenant** : elle n'est là que si une énigme de son lieu est
 * jouable (bâtiment debout, énigme non encore tentée). Une fois toutes jouées, elle s'en va. Et elle
 * **n'est qu'un renvoi** vers l'énigme existante : pas un mot de son énoncé, jamais sa réponse.
 * La Maison des scribes n'a pas la sienne : ses scribes sont déjà en ville.
 */
final class VisiteurDePassage
{
    /**
     * @return list<array{cle: string, role: RoleDeFigure, lieu: TypeDeBatiment, libelle: string, nombre: int}>
     */
    public static function pour(City $ville): array
    {
        $parLieu = [];

        foreach (Enigme::disponiblesPour($ville) as $enigme) {
            $parLieu[$enigme->lieu()->value] = ($parLieu[$enigme->lieu()->value] ?? 0) + 1;
        }

        $visiteurs = [];

        foreach ([
            [TypeDeBatiment::Auberge, RoleDeFigure::Voyageur, 'voyageur', 'Un voyageur de passage'],
            [TypeDeBatiment::Temple, RoleDeFigure::Pretre, 'pretre', 'Un prêtre de passage'],
        ] as [$lieu, $role, $cle, $libelle]) {
            if (isset($parLieu[$lieu->value])) {
                $visiteurs[] = [
                    'cle' => 'visiteur-'.$cle,
                    'role' => $role,
                    'lieu' => $lieu,
                    'libelle' => $libelle,
                    'nombre' => $parLieu[$lieu->value],
                ];
            }
        }

        return $visiteurs;
    }

    /**
     * Ce que dit sa fiche : une invitation, jamais un énoncé.
     */
    public static function invitation(RoleDeFigure $role): string
    {
        return RoleDeFigure::Pretre === $role
            ? 'Une question pour qui sait écouter les dieux ?'
            : 'Une devinette, étranger ?';
    }
}
