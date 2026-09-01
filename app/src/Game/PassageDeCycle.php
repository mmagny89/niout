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
        private Fabrication $fabrication,
        private Commerce $commerce,
        private Mecontentement $mecontentement,
        private Negligence $negligence,
        private Providence $providence,
        private Epidemies $epidemies,
        private Rivaux $rivaux,
        private AchevementDeMission $achevement,
        private DepartsNaturels $departs,
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
        // La fièvre se résout **avant** la paie et la production : elle
        // décide de combien de bras la quinzaine dispose, et il serait faux
        // de faire travailler des gens que la maladie couche le même jour.
        $fievre = $this->epidemies->avancerDUnCycle($partie);

        $paie = $this->salaires->reglerLaQuinzaine($partie);

        $subsistance = null;

        $evenements = [
            ...$fievre,
            ...$paie->messages,
            ...$this->explorations->avancerDUnCycle($partie),
            ...$this->chantiers->avancerDUnCycle($partie, $saison),
            // Les ateliers avancent avec les chantiers : même nature d'ouvrage,
            // et leurs pièces doivent être au stock avant que la ville ne mange
            // — le pain et la bière sont des vivres.
            ...$this->fabrication->avancerDUnCycle($partie),
            // Les caravanes en chemin se rapprochent : ouvrir une route prend
            // le temps du trajet, comme une expédition.
            ...$this->commerce->avancerDUnCycle($partie),
            ...$this->recoltes->avancerDUnCycle($partie, $paie, $this->mecontentement->rendementEnCentiemes($partie)),
        ];

        // Après la récolte, jamais avant : la ville mange ce que la quinzaine
        // vient d'apporter.
        $subsistance = $this->subsistance->avancerDUnCycle($partie);
        $evenements = [...$evenements, ...$subsistance['evenements']];

        // Les deux causes se rejoignent ici, et nulle part ailleurs : on ne
        // mange pas, ou l'on n'est pas payé. Le mécontentement pèse ensuite
        // sur la quinzaine suivante — jamais sur celle qui vient de le
        // produire, sans quoi une seule mauvaise quinzaine se paierait deux
        // fois.
        $this->mecontentement->enregistrer($partie, $subsistance['famine'], !$paie->toutEstPaye());
        $evenements = [
            ...$evenements,
            ...$this->mecontentement->raconter($partie),
            ...$this->departs->avancerDUnCycle($partie),
            // Les dieux comptent les quinzaines depuis la dernière offrande.
            // Après le reste : ce qu'on a produit et mangé cette quinzaine ne
            // dépend pas d'eux, seule la suivante s'en ressentira.
            ...$this->negligence->avancerDUnCycle($partie),
            // Puis ce que les dieux font d'eux-mêmes, une fois leur palier à
            // jour : bénédiction d'un dévoué, revers d'un hostile.
            ...$this->providence->avancerDUnCycle($partie),
            // Et ce que la renommée attire : un marchand qui vient disputer
            // une route (doc 08).
            ...$this->rivaux->avancerDUnCycle($partie),
        ];

        $partie->avancerDUnCycle();

        // Tout ce qui se compte à l'année se résout ici, une fois la bascule
        // franchie — et pas au premier cycle d'une partie, où la ville vient
        // tout juste d'arriver.
        if ($partie->dateDeJeu()->ouvreUneAnnee()) {
            // La crue est annoncée avant qu'on ait à semer : c'est un aléa
            // qu'on subit, pas une surprise de moisson.
            // Hâpi n'ajoute pas un facteur à la récolte : il infléchit le
            // tirage, d'un cran, dans un sens ou dans l'autre (lot 6.3).
            $crue = EffetDeFaveur::crueInflechie($partie->getVille(), $this->crues->tirer());
            $partie->annoncerLaCrue($crue);
            $evenements[] = \sprintf('La crue de cette année est %s. %s', $crue->libelle(), $crue->presage());

            // Puis le bilan des habitants : qui entre dans la vie active, qui
            // s'en retire, qui s'éteint.
            $evenements = [...$evenements, ...$this->demographie->bilanDeLAnnee($partie)];
        }

        // En dernier : la mission peut s'être accomplie dans cette quinzaine,
        // et il faut que tout ce qui la mesure soit déjà à jour.
        $evenements = [...$evenements, ...$this->achevement->verifier($partie)];

        $this->entityManager->flush();

        return $evenements;
    }
}
