<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le battement du jeu : ce qui se résout quand le joueur avance d'une quinzaine.
 *
 * Réunit ici tout ce qui progresse dans le même cycle — chantiers, expéditions,
 * et demain récoltes et événements. Un seul endroit décide de l'ordre des
 * résolutions et n'écrit qu'une fois.
 */
final readonly class PassageDeCycle
{
    public function __construct(
        private Chantiers $chantiers,
        private Explorations $explorations,
        private Recoltes $recoltes,
        private Demographie $demographie,
        private Subsistance $subsistance,
        private Salaires $salaires,
        private TirageDeLaCrue $crues,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function passer(GameSave $partie): array
    {
        // La saison du cycle qu'on vient de vivre, pas celle du suivant : les
        // travaux, les trajets et les récoltes ont eu lieu pendant l'ancien.
        $saison = $partie->dateDeJeu()->saison;

        // La paie d'abord : une équipe qu'on n'a pas pu payer ne travaille pas
        // dans la quinzaine qu'elle ouvre. La calculer après la production
        // reviendrait à faire travailler puis à ne pas payer, ce qui ne
        // laisserait au joueur aucune décision à prendre.
        $paie = $this->salaires->reglerLaQuinzaine($partie);

        $evenements = [
            ...$paie->messages,
            ...$this->explorations->avancerDUnCycle($partie),
            ...$this->chantiers->avancerDUnCycle($partie, $saison),
            ...$this->recoltes->avancerDUnCycle($partie, $paie),
            // Après la récolte, jamais avant : la ville mange ce que la
            // quinzaine vient d'apporter.
            ...$this->subsistance->avancerDUnCycle($partie),
        ];

        $partie->avancerDUnCycle();

        // Tout ce qui se compte à l'année se résout ici, une fois la bascule
        // franchie — et pas au premier cycle d'une partie, où la ville vient
        // tout juste d'arriver.
        if ($partie->dateDeJeu()->ouvreUneAnnee()) {
            // La crue est annoncée avant qu'on ait à semer : c'est un aléa
            // qu'on subit, pas une surprise de moisson.
            $crue = $this->crues->tirer();
            $partie->annoncerLaCrue($crue);
            $evenements[] = \sprintf('La crue de cette année est %s. %s', $crue->libelle(), $crue->presage());

            // Puis le bilan des habitants : qui entre dans la vie active, qui
            // s'en retire, qui s'éteint.
            $evenements = [...$evenements, ...$this->demographie->bilanDeLAnnee($partie)];
        }

        $this->entityManager->flush();

        return $evenements;
    }
}
