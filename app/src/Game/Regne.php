<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un règne de la succession du mode Aventure (doc 14).
 *
 * Donnée de référence, jamais persistée : c'est du contenu, au même titre que
 * les missions de la campagne. Le règne en cours se **déduit du cycle**, ce qui
 * évite une colonne et rend la liste allongeable sans migration.
 */
final readonly class Regne
{
    public function __construct(
        /** Le nom sous lequel le pharaon est connu — la clé de `CartoucheRoyal::pourLePharaon()`. */
        public string $pharaon,
        /** La dynastie, en chiffres : 18, 19, 20. */
        public int $dynastie,
        /** Les années de règne réelles, arrondies — elles décident de la catégorie, jamais de la durée en jeu. */
        public int $anneesReelles,
        /** La durée en cycles de jeu, dans la fourchette de sa catégorie. */
        public int $dureeEnCycles,
        /** Ce que le joueur lit à l'avènement : un fait, jamais une invention. */
        public string $avenement,
    ) {
    }

    public function longueur(): LongueurDeRegne
    {
        return LongueurDeRegne::pourDesAnnees($this->anneesReelles);
    }

    /**
     * Son cartouche, s'il est établi. **Nul plutôt qu'approximatif** : la règle
     * des hiéroglyphes vaut ici comme partout — un cartouche dont la lecture ne
     * s'établit pas ne s'affiche pas.
     */
    /**
     * Son nom de trône — **lu sur le cartouche, jamais recopié à côté**.
     *
     * Les deux ont divergé le jour où on les a écrits deux fois : `Nebpehtyré`
     * ici, `Nebpehtyrê` là. Une seule source, et la question ne se pose plus.
     */
    public function nomDeTrone(): ?string
    {
        return $this->cartouche()?->lecture();
    }

    public function cartouche(): ?CartoucheRoyal
    {
        return CartoucheRoyal::pourLePharaon($this->pharaon);
    }
}
