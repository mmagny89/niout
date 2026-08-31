<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les enquêtes (doc 10).
 *
 * Une enquête n'est pas une énigme : elle **se construit**. On ramasse des
 * indices au fil de l'exploration et des rencontres, on les recoupe, et l'on
 * conclut — parfois de travers.
 *
 * **Principale ou secondaire, et la différence est structurante** (décision de
 * la joueuse) : une enquête **principale** porte le fil rouge d'une mission,
 * donc elle se rejoue jusqu'à être résolue — son échec définitif bloquerait la
 * campagne. Une enquête **secondaire** peut se perdre pour de bon, et c'est ce
 * qui donne du poids à une déduction : sans ce risque, conclure au hasard puis
 * recommencer serait toujours la meilleure stratégie.
 *
 * **Trois à cinq indices, dont certains optionnels ou trompeurs** (doc 10).
 * C'est ce qui distingue une enquête d'une case à cocher : si tous les indices
 * concouraient, il suffirait de les compter.
 */
enum Enquete: string
{
    case PassageCoupe = 'passage_coupe';
    case CarrieresAbandonnees = 'carrieres_abandonnees';
    case RumeurDeLaCaravane = 'rumeur_de_la_caravane';

    public function libelle(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Le passage coupé',
            self::CarrieresAbandonnees => 'Les carrières abandonnées',
            self::RumeurDeLaCaravane => 'La rumeur de la caravane',
        };
    }

    public function question(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Une terre fertile, à deux pas de la ville, que personne ne cultive. Pourquoi la laisse-t-on ?',
            self::CarrieresAbandonnees => 'Des outils rouillés, des cabanes vides : on a extrait ici, puis on est parti. Qu\'a-t-on fui ?',
            self::RumeurDeLaCaravane => 'Deux caravaniers racontent la même route, et ne s\'accordent sur rien. Lequel ment ?',
        };
    }

    /**
     * Une principale porte le fil rouge d'une mission ; elle se rejoue jusqu'à
     * être résolue. Une secondaire peut se perdre.
     */
    public function estPrincipale(): bool
    {
        return self::PassageCoupe === $this;
    }

    /**
     * Combien d'indices concordants il faut avoir réunis pour pouvoir
     * conclure. Les fausses pistes n'y comptent pas — c'est précisément ce
     * qu'on doit démêler.
     */
    public function indicesRequis(): int
    {
        return match ($this) {
            self::PassageCoupe => 3,
            self::CarrieresAbandonnees => 2,
            self::RumeurDeLaCaravane => 2,
        };
    }

    /**
     * @return list<Indice>
     */
    public function indices(): array
    {
        $siens = [];

        foreach (Indice::cases() as $indice) {
            if ($indice->enquete() === $this) {
                $siens[] = $indice;
            }
        }

        return $siens;
    }
}
