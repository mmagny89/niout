<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Game\AvantageDeNegoce;
use App\Game\CarnetDeContacts;
use App\Game\CatalogueDeLaVille;
use App\Game\Commerce;
use App\Game\EtatDeLaVille;
use App\Game\FilRouge;
use App\Game\Impots;
use App\Game\LeconDeNiout;
use App\Game\Mecontentement;
use App\Game\MissionCatalogue;
use App\Game\ObjectifDeMission;
use App\Game\ObjectifsDeMission;
use App\Game\PalierDErudition;
use App\Game\Salaires;
use App\Game\ScoreDAventure;
use App\Game\SuccessionFamiliale;
use App\Game\Successions;
use App\Game\TravauxEnCours;
use App\Game\TypeDeBatiment;

/**
 * Le panneau de la Résidence familiale : **tout ce qui n'appartient à aucun
 * bâtiment**. Elle est le foyer de la lignée, présente dès le premier jour et
 * jamais construite : la mission, la renommée, les chantiers et la liste de ce
 * qui reste à bâtir y vivent — les envoyer ailleurs les rendrait inaccessibles à
 * une ville qui n'a encore rien dressé.
 */
final readonly class PanneauDeLaResidenceFamiliale implements FournisseurDePanneau
{
    public function __construct(
        private EtatDeLaVille $etat,
        private MissionCatalogue $missions,
        private CatalogueDeLaVille $catalogue,
        private TravauxEnCours $travaux,
        private Salaires $salaires,
        private Impots $impots,
        private Mecontentement $mecontentement,
        private Commerce $commerce,
        private CarnetDeContacts $carnet,
        private Successions $successions,
        private ScoreDAventure $score,
        private SuccessionFamiliale $successionFamiliale,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::ResidenceFamiliale->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $famille = $partie->getFamille();
        $mission = $this->missions->de($partie);

        return [
            // Le bon comme le mauvais : un joueur ne doit pas changer de page
            // pour savoir où en est sa ville.
            'ennuis' => $this->etat->ennuis($partie),
            'bonnesNouvelles' => $this->etat->bonnesNouvelles($partie),
            'autonomie' => $this->etat->autonomieEnVivres($partie),
            'quinzainesDeVivresInquietantes' => EtatDeLaVille::QUINZAINES_DE_VIVRES_INQUIETANTES,
            'chantiers' => $ville->getChantiers(),
            'travauxEnCours' => $this->travaux->pour($partie),
            'batimentsDresses' => OrdreDesBatiments::parLibelle($ville),
            'offres' => $this->catalogue->pour($ville),
            'salaireMinimal' => City::SALAIRE_MINIMAL,
            'salaireMaximal' => City::SALAIRE_MAXIMAL,
            'salaireJuste' => City::SALAIRE_JUSTE,
            'salaireGenereux' => Mecontentement::SALAIRE_GENEREUX,
            'griefsDeLaVille' => Mecontentement::griefs($ville),
            // La renommée : ce qu'elle change — le prix d'un appel, la
            // migration spontanée, l'arrivée d'un rival — se subissait sans se
            // comprendre.
            'palier' => $famille->palier(),
            'palierSuivant' => $famille->palier()->suivant(),
            'seuilDuPalierSuivant' => $famille->palier()->suivant()?->seuilDEntree() ?? Family::RENOMMEE_MAX,
            'renommeeMax' => Family::RENOMMEE_MAX,
            // Ce que la renommée vaut concrètement sur les prix (lot 9.3).
            'avantageDeRenommee' => AvantageDeNegoce::deLaRenommee($famille->getRenommee()),
            'avantageDeNegoce' => $this->commerce->avantageDeNegoce($partie),
            // Les villes où la famille a déjà servi (lot 9.4) : elles font un
            // prix sur ce que leur région porte.
            'carnet' => $this->carnet->lisible($partie),
            // Le règne en cours du mode Aventure (lot 11.1) : la ville n'y a
            // pas de commanditaire, elle traverse des souverains.
            'regne' => $this->successions->regneEnCours($partie),
            'rangDuRegne' => $this->successions->rangEnCours($partie),
            'nombreDeRegnes' => $this->successions->nombreDeRegnes(),
            // Le score cumulatif du mode Aventure (lot 11.4) : et le détail,
            // car un total nu ne dit pas quoi faire pour le faire monter.
            'scoreDAventure' => $partie->estCampagne() ? null : $this->score->total($partie),
            'detailDuScore' => $partie->estCampagne() ? [] : $this->score->detail($partie),
            // La succession familiale (lot 11.5) : elle ne s'ouvre qu'une fois
            // la génération faite, et le jeu attend alors un choix.
            'heritiers' => $this->successionFamiliale->heritiers($partie),
            'chefDeFamille' => $famille->getChefDeFamille(),
            'generation' => $famille->getGeneration(),
            'traitDeLignee' => $famille->getTraitDeLignee(),
            'masseSalariale' => $this->salaires->masseSalariale($ville, $partie->getCycle()),
            // L'impôt du mois : le filet qui renfloue la caisse, dit avec son
            // chiffre et son échéance plutôt que découvert à la perception.
            'impotPrevu' => $this->impots->montantPrevu($partie),
            'quinzainesAvantImpot' => $this->impots->quinzainesAvantLaPerception($partie),
            'erudition' => PalierDErudition::pour($ville, $partie->getCycle()),
            'nombreDeSignesAppris' => PalierDErudition::signesConnus($ville, $partie->getCycle()),
            'motDeNiout' => LeconDeNiout::motEcrit(),
            'totalDesSignes' => PalierDErudition::signesEnTout(),
            'maisonDesScribesDressee' => $ville->possede(TypeDeBatiment::MaisonDesScribes),
            'mecontentement' => $partie->getQuinzainesDeMecontentement(),
            'villeMecontente' => $this->mecontentement->pese($partie),
            'filRouge' => FilRouge::court($partie) ? FilRouge::acte($partie) : null,
            // Les objectifs sont affichés dès le premier jour (doc 09) : la
            // transparence évite de découvrir tardivement des conditions
            // qu'on n'a pas pu anticiper.
            'mission' => $mission,
            'quete' => $ville->getQueteDeChantier(),
            'objectifs' => array_map(
                static fn (ObjectifDeMission $objectif): array => [
                    'objectif' => $objectif,
                    'avancement' => $objectif->avancement($partie),
                    'atteint' => $objectif->estAtteint($partie),
                ],
                null !== $mission ? ObjectifsDeMission::pour($mission) : [],
            ),
        ] + $this->direction->donnees($partie);
    }
}
