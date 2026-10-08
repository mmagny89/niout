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
        private Successions $successions,
        private Chantiers $chantiers,
        private Explorations $explorations,
        private Recoltes $recoltes,
        private Demographie $demographie,
        private Subsistance $subsistance,
        private Salaires $salaires,
        private GeographieDeLaPartie $geographies,
        private Fabrication $fabrication,
        private Commerce $commerce,
        private Marche $marche,
        private Impots $impots,
        private Mecontentement $mecontentement,
        private Negligence $negligence,
        private Providence $providence,
        private Epidemies $epidemies,
        private Rivaux $rivaux,
        private QuetesDeChantier $quetes,
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
        return array_map(
            static fn (array $evenement): string => $evenement['texte'],
            $this->passerEnDetail($partie),
        );
    }

    /**
     * Comme `passer()`, mais chaque ligne dit d'où elle vient : le récapitulatif de la
     * quinzaine les range et leur donne un pictogramme.
     *
     * @return list<array{categorie: CategorieDEvenement, texte: string}>
     */
    public function passerEnDetail(GameSave $partie): array
    {
        $de = static fn (CategorieDEvenement $categorie, array $messages): array => array_map(
            static fn (string $texte): array => ['categorie' => $categorie, 'texte' => $texte],
            array_values($messages),
        );

        // La saison du cycle qu'on vient de vivre, pas celle du suivant : les
        // travaux, les trajets et les récoltes ont eu lieu pendant l'ancien.
        // **Sans Nil, il n'y a ni crue ni saison d'inondation** (doc 02) : la
        // corvée d'Akhèt mobilisait les paysans que le fleuve désœuvrait, et
        // rien ne les désœuvre au Sinaï. Les chantiers y avancent au rythme
        // ordinaire, comme pendant les cinq jours hors saison.
        $connaitLaCrue = $this->geographies->connaitLaCrue($partie);
        $saison = $connaitLaCrue ? $partie->dateDeJeu()->saison : null;

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
            ...$de(CategorieDEvenement::Sante, $fievre),
            ...$de(CategorieDEvenement::Bourse, $paie->messages),
            ...$de(CategorieDEvenement::Expeditions, $this->explorations->avancerDUnCycle($partie)),
            ...$de(CategorieDEvenement::Chantiers, $this->chantiers->avancerDUnCycle($partie, $saison)),
            // Les ateliers avancent avec les chantiers : même nature d'ouvrage,
            // et leurs pièces doivent être au stock avant que la ville ne mange
            // — le pain et la bière sont des vivres.
            ...$de(CategorieDEvenement::Ateliers, $this->fabrication->avancerDUnCycle($partie)),
            // Les caravanes en chemin se rapprochent : ouvrir une route prend
            // le temps du trajet, comme une expédition.
            ...$de(CategorieDEvenement::Commerce, $this->commerce->avancerDUnCycle($partie)),
            ...$de(CategorieDEvenement::Vivres, $this->recoltes->avancerDUnCycle($partie, $paie, $this->mecontentement->rendementEnCentiemes($partie))),
        ];

        // Après la récolte, jamais avant : la ville mange ce que la quinzaine
        // vient d'apporter.
        $subsistance = $this->subsistance->avancerDUnCycle($partie);
        $evenements = [...$evenements, ...$de(CategorieDEvenement::Vivres, $subsistance['evenements'])];

        // Le jour de marché, une fois la ville nourrie : les habitants
        // achètent ce qu'on leur a laissé à l'étal. **Après la subsistance,
        // jamais avant** — un étal garni de blé aurait sinon pu vendre la
        // ration du jour et affamer la ville pour quelques deben, ce qu'aucun
        // joueur n'aurait vu venir.
        //
        // Il consomme le débouché de la quinzaine qui se solde, comme les
        // ventes faites à la main pendant celle-ci : c'est la même place, elle
        // ne se sature qu'une fois.
        $evenements = [...$evenements, ...$de(CategorieDEvenement::Commerce, $this->marche->tenirLEtal($partie))];

        // L'impôt du mois, une fois la quinzaine payée et nourrie : il
        // renfloue la caisse pour la suivante, il ne rembourse pas celle qui
        // se solde — sans quoi une paie impayée serait rattrapée après coup.
        $evenements = [...$evenements, ...$de(CategorieDEvenement::Bourse, $this->impots->percevoir($partie))];

        // Les deux causes se rejoignent ici, et nulle part ailleurs : on ne
        // mange pas, ou l'on n'est pas payé. Le mécontentement pèse ensuite
        // sur la quinzaine suivante — jamais sur celle qui vient de le
        // produire, sans quoi une seule mauvaise quinzaine se paierait deux
        // fois.
        $this->mecontentement->enregistrer($partie, $subsistance['famine'], !$paie->toutEstPaye());
        $evenements = [
            ...$evenements,
            ...$de(CategorieDEvenement::Habitants, $this->mecontentement->raconter($partie)),
            ...$de(CategorieDEvenement::Habitants, $this->departs->avancerDUnCycle($partie)),
            // Les dieux comptent les quinzaines depuis la dernière offrande.
            // Après le reste : ce qu'on a produit et mangé cette quinzaine ne
            // dépend pas d'eux, seule la suivante s'en ressentira.
            ...$de(CategorieDEvenement::Dieux, $this->negligence->avancerDUnCycle($partie)),
            // Puis ce que les dieux font d'eux-mêmes, une fois leur palier à
            // jour : bénédiction d'un dévoué, revers d'un hostile.
            ...$de(CategorieDEvenement::Dieux, $this->providence->avancerDUnCycle($partie)),
            // Et ce que la renommée attire : un marchand qui vient disputer
            // une route (doc 08).
            ...$de(CategorieDEvenement::Rivaux, $this->rivaux->avancerDUnCycle($partie)),
            // Et ce que le pharaon réclame pour ses propres chantiers.
            ...$de(CategorieDEvenement::Royaume, $this->quetes->avancerDUnCycle($partie)),
        ];

        $partie->avancerDUnCycle();

        // Nouveau jour de marché : la place peut de nouveau absorber du
        // surplus. Après `avancerDUnCycle()`, jamais avant — le débouché
        // appartient à la quinzaine qui s'ouvre, pas à celle qui se solde.
        $partie->getVille()->rouvrirLEtal();

        // **L'avènement se dit après la bascule**, jamais avant : c'est la
        // quinzaine qui s'ouvre qui voit le nouveau roi, pas celle qui se
        // solde sous l'ancien (doc 14, lot 11.1).
        foreach ($this->successions->avenementAuCycle($partie) as $annonce) {
            $evenements[] = ['categorie' => CategorieDEvenement::Royaume, 'texte' => $annonce];
        }

        // Tout ce qui se compte à l'année se résout ici, une fois la bascule
        // franchie — et pas au premier cycle d'une partie, où la ville vient
        // tout juste d'arriver.
        if ($connaitLaCrue && $partie->dateDeJeu()->ouvreUneAnnee()) {
            // La crue est annoncée avant qu'on ait à semer : c'est un aléa
            // qu'on subit, pas une surprise de moisson.
            // Hâpi n'ajoute pas un facteur à la récolte : il infléchit le
            // tirage, d'un cran, dans un sens ou dans l'autre (lot 6.3).
            $crue = EffetDeFaveur::crueInflechie($partie->getVille(), $this->crues->tirer());
            $partie->annoncerLaCrue($crue);
            $evenements[] = ['categorie' => CategorieDEvenement::Calendrier, 'texte' => \sprintf('La crue de cette année est %s. %s', $crue->libelle(), $crue->presage())];
        }

        // Le bilan des habitants, lui, tombe partout : on naît et l'on meurt
        // au Sinaï comme au Delta. Le sortir du bloc de la crue est ce qui
        // évite qu'une région sans fleuve cesse de vieillir.
        if ($partie->dateDeJeu()->ouvreUneAnnee()) {
            $evenements = [...$evenements, ...$de(CategorieDEvenement::Habitants, $this->demographie->bilanDeLAnnee($partie))];
        }

        // En dernier : la mission peut s'être accomplie dans cette quinzaine,
        // et il faut que tout ce qui la mesure soit déjà à jour.
        $evenements = [...$evenements, ...$de(CategorieDEvenement::Royaume, $this->achevement->verifier($partie))];

        $this->entityManager->flush();

        return $evenements;
    }
}
