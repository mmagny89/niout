<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\DossierDEnquete;

/**
 * La mémoire du dossier (lot I4 du plan « dialogues-indices »).
 *
 * Le chef de la Maison des scribes **remet sous les yeux ce que le joueur a déjà noté**, et pose la
 * question de la déduction **sans y répondre**. Il organise le raisonnement, il ne le raccourcit pas.
 *
 * **Tout nommer, ou rien.** C'est la seule règle qui compte ici : choisir ce qu'on rappelle suffirait à
 * trahir la nature d'un indice (ne citer que les concordants dirait tout). Le résumé nomme donc **chaque**
 * indice du dossier, **dans l'ordre où ils sont venus**, avec la même phrase que le dossier — ou il se
 * tait. La phrase d'introduction et la question sont **les mêmes pour tout dossier** : seul le nombre
 * change. Aucun mot de jugement, aucune réponse, aucun indice créé (le dossier n'est pas touché).
 */
final class MemoireDuDossier
{
    /**
     * Il faut au moins deux indices pour que « remettre en ordre » ait un sens.
     */
    public const int INDICES_AU_MOINS = 2;

    public const string QUESTION = 'Qu\'en penses-tu ?';

    /**
     * @return array{voix: string, constat: string, lignes: list<string>, question: string}|null
     */
    public static function pour(City $ville, DossierDEnquete $dossier): ?array
    {
        $indices = $dossier->indices();

        if (StatutDEnquete::EnCours !== $dossier->getStatut() || \count($indices) < self::INDICES_AU_MOINS) {
            return null;
        }

        $prenom = null;

        foreach ($ville->chefsDe(TypeDeBatiment::MaisonDesScribes) as $chef) {
            $prenom = $chef->getPrenom();

            if (null !== $prenom) {
                break;
            }
        }

        // Sans chef nommé à la Maison des scribes, personne ne parle : le dossier se lit seul.
        if (null === $prenom) {
            return null;
        }

        return [
            'voix' => $prenom,
            'constat' => \sprintf('J\'ai relu le dossier : %d choses notées, dans l\'ordre où elles sont venues.', \count($indices)),
            'lignes' => array_map(
                static fn (Indice $indice): string => null === $indice->parole()
                    ? $indice->texte()
                    : \sprintf('%s : « %s »', (string) $indice->temoin(), $indice->parole()),
                $indices,
            ),
            'question' => self::QUESTION,
        ];
    }
}
