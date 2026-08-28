<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un candidat qui se présente à une offre d'emploi (doc 03).
 *
 * **Jamais une entité** : une candidature n'existe que le temps du choix, et
 * ce qui survit à l'embauche est l'`Employee` qui s'installe. Elle est
 * cependant *sérialisée* dans l'offre qui la porte (`JobOffer`) — sans quoi
 * recharger la page retirerait les candidats, offrant au joueur autant de
 * relances gratuites qu'il en veut. L'offre fige donc son tirage, comme une
 * annonce affichée fige les réponses reçues.
 *
 * **Chiffré en interne, qualitatif à l'affichage.** Le moteur manipule une
 * compétence de 20 à 100 ; le joueur ne doit voir que des étoiles et des
 * libellés. `competence` reste accessible parce que les calculs de production
 * en ont besoin (lot 4.8) — mais **aucun gabarit ne doit l'imprimer**, sous
 * peine de rendre au joueur une précision que le document lui refuse
 * délibérément. Pour l'affichage : `etoiles()` et `esperanceDeService()`.
 *
 * Deux exceptions assumées : le **salaire**, déjà qualitatif par nature — un
 * prix se lit tel quel —, et la **maisonnée** qu'il amène, qui décide de ce
 * qu'il coûtera à nourrir et des bras qu'il apporte.
 */
final readonly class Candidat
{
    /**
     * @param list<TraitDeCandidat> $traits un ou deux, parfois aucun
     */
    public function __construct(
        public int $competence,
        public int $salaire,
        public int $ancienneteProbable,
        public array $traits,
        public ?SpecialiteDeChef $specialite,
        public int $actifsAmenes,
        public int $inactifsAmenes,
    ) {
    }

    /**
     * Sérialise pour l'offre qui porte cette candidature. Les traits et la
     * spécialité descendent à leur `value` : c'est ce qui rend la ligne
     * relisible après un changement de code, tant que les cas existent.
     *
     * @return array{competence: int, salaire: int, anciennete: int, traits: list<string>, specialite: ?string, actifs: int, inactifs: int}
     */
    public function enTableau(): array
    {
        return [
            'competence' => $this->competence,
            'salaire' => $this->salaire,
            'anciennete' => $this->ancienneteProbable,
            'traits' => array_map(static fn (TraitDeCandidat $trait): string => $trait->value, $this->traits),
            'specialite' => $this->specialite?->value,
            'actifs' => $this->actifsAmenes,
            'inactifs' => $this->inactifsAmenes,
        ];
    }

    /**
     * Relit une candidature sérialisée. Un trait ou une spécialité devenu
     * inconnu est **ignoré** plutôt que fatal : une offre en cours ne doit pas
     * rendre une partie illisible parce que la table a changé entre-temps.
     *
     * @param array<string, mixed> $ligne
     */
    public static function depuisTableau(array $ligne): self
    {
        $traits = [];

        foreach (\is_array($ligne['traits'] ?? null) ? $ligne['traits'] : [] as $valeur) {
            $trait = \is_string($valeur) ? TraitDeCandidat::tryFrom($valeur) : null;

            if (null !== $trait) {
                $traits[] = $trait;
            }
        }

        return new self(
            competence: (int) ($ligne['competence'] ?? 0),
            salaire: (int) ($ligne['salaire'] ?? 0),
            ancienneteProbable: (int) ($ligne['anciennete'] ?? 0),
            traits: $traits,
            specialite: \is_string($ligne['specialite'] ?? null) ? SpecialiteDeChef::tryFrom($ligne['specialite']) : null,
            actifsAmenes: (int) ($ligne['actifs'] ?? 0),
            inactifsAmenes: (int) ($ligne['inactifs'] ?? 0),
        );
    }

    /**
     * Les traits qui ne produisent encore aucun effet, faute des systèmes qui
     * les emploieront (faveur divine en Phase 6, combat en Phase 10).
     * L'écran doit le dire : laisser croire à un bonus actif serait mentir au
     * joueur sur ce qu'il paie.
     *
     * @return list<TraitDeCandidat>
     */
    public function traitsEndormis(): array
    {
        return array_values(array_filter(
            $this->traits,
            static fn (TraitDeCandidat $trait): bool => $trait->dortEnAttendantSaPhase(),
        ));
    }

    /**
     * Le barème du doc 03 : 20-36 ★, 37-52 ★★, 53-68 ★★★, 69-84 ★★★★,
     * 85-100 ★★★★★.
     */
    public function etoiles(): int
    {
        return match (true) {
            $this->competence <= 36 => 1,
            $this->competence <= 52 => 2,
            $this->competence <= 68 => 3,
            $this->competence <= 84 => 4,
            default => 5,
        };
    }

    /**
     * L'ancienneté, traduite en libellé (doc 03) : le joueur doit sentir s'il
     * embauche pour longtemps, sans lire un nombre de quinzaines.
     */
    public function esperanceDeService(): string
    {
        return match (true) {
            $this->ancienneteProbable >= 24 => 'Devrait rester longtemps',
            $this->ancienneteProbable >= 17 => 'Devrait tenir son poste',
            default => 'Risque de partir bientôt',
        };
    }

    /**
     * Les habitants que ce candidat installerait — lui, les siens, ceux qui
     * travaillent comme ceux qui sont à charge.
     */
    public function personnesAmenees(): int
    {
        return $this->actifsAmenes + $this->inactifsAmenes;
    }

    /**
     * Ce que sa maisonnée mangera, en demi-rations : deux par actif, une par
     * inactif. C'est ce qui permet au joueur de comparer le coût réel de deux
     * candidats au même salaire.
     */
    public function demiRationsAmenees(): int
    {
        return $this->actifsAmenes * 2 + $this->inactifsAmenes;
    }
}
