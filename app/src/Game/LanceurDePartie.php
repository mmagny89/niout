<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée une partie, dans l'un ou l'autre mode, et la dote.
 *
 * Concentre ici les règles de lancement — plafond de parties, ville imposée,
 * dotation royale — pour que le contrôleur n'ait qu'à transmettre le choix du
 * joueur.
 */
final readonly class LanceurDePartie
{
    /**
     * Le mode Aventure se joue toujours à Memphis : capitale historique
     * traversant la quasi-totalité du Nouvel Empire, et absente de la campagne
     * (doc 14).
     */
    public const string VILLE_DU_MODE_AVENTURE = 'Memphis';

    public function __construct(
        private MissionCatalogue $missions,
        private GameSaveRepository $parties,
        private GenerateurDeCarte $carte,
        private GenerateurDeFoyer $foyers,
        private TirageDeLaCrue $crues,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws PlafondDePartiesAtteint
     */
    public function lancerCampagne(User $joueur, string $nomDeFamille): GameSave
    {
        $this->refuserSiPlafondAtteint($joueur);

        $mission = $this->missions->get(GameSave::PREMIERE_MISSION);
        $ville = new City($mission->ville, $mission->difficulte, $mission->tailleDeGrille());

        $partie = GameSave::pourCampagne($joueur, new Family($nomDeFamille), $ville);

        return $this->doterEtEnregistrer($partie, $mission->geographie);
    }

    /**
     * @throws PlafondDePartiesAtteint
     */
    public function lancerAventure(User $joueur, string $nomDeFamille, int $difficulte, int $tailleGrille): GameSave
    {
        $this->refuserSiPlafondAtteint($joueur);

        $ville = new City(self::VILLE_DU_MODE_AVENTURE, $difficulte, $tailleGrille);
        $partie = GameSave::pourAventure($joueur, new Family($nomDeFamille), $ville);

        return $this->doterEtEnregistrer($partie, self::geographieDuModeAventure());
    }

    /**
     * Memphis borde le Nil et jouxte le plateau de Saqqara, immense nécropole
     * d'où vient son natron. Trop au sud pour la Méditerranée (doc 14).
     */
    public static function geographieDuModeAventure(): GeographieDeRegion
    {
        return new GeographieDeRegion(
            nil: true,
            desert: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire, Ressource::Natron],
        );
    }

    private function doterEtEnregistrer(GameSave $partie, GeographieDeRegion $geographie): GameSave
    {
        $ville = $partie->getVille();

        // La carte naît avec la partie : une ville sans territoire n'aurait
        // pas de sens, et l'engendrer plus tard laisserait un état à moitié
        // initialisé.
        $this->carte->peupler($ville, $geographie);

        // La famille fondatrice s'installe avant la dotation : c'est elle qui
        // en fixe les vivres, le pharaon envoyant une année de rations pour
        // le foyer qu'il expédie, pas pour un foyer moyen.
        $ville->ajouterFoyer($this->foyers->pour($ville));

        $dotation = DotationRoyale::pour($ville->getDifficulte(), $ville->consommationDeNourriture());
        $ville->crediterRessources($dotation->enRessources());

        // La crue de la première année est déjà jouée quand le joueur arrive :
        // elle conditionne la moisson qu'il prépare dès maintenant.
        $partie->annoncerLaCrue($this->crues->tirer());

        // La Résidence familiale est le foyer de la lignée : elle est là dès
        // l'arrivée, jamais construite ni payée (doc 01).
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::ResidenceFamiliale));

        $this->entityManager->persist($partie);
        $this->entityManager->flush();

        return $partie;
    }

    /**
     * @throws PlafondDePartiesAtteint
     */
    private function refuserSiPlafondAtteint(User $joueur): void
    {
        if ($this->parties->plafondAtteintPour($joueur)) {
            throw new PlafondDePartiesAtteint();
        }
    }
}
