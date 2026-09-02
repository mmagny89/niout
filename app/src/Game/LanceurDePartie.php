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
        private TirageDeLaCrue $crues,
        private EntityManagerInterface $entityManager,
        private Progression $progression,
        private Legs $legs,
        private Lignees $lignees,
        private BonusDeDepart $bonus,
    ) {
    }

    /**
     * @throws PlafondDePartiesAtteint
     */
    /**
     * `$numeroDeMission` n'est renseigné que par le **mode divin**, qui ouvre
     * les dix régions pour les essais (`User::ROLE_DIVIN`). Une campagne
     * ordinaire démarre toujours à la première : l'ordre est imposé (doc 09).
     */
    public function lancerCampagne(User $joueur, string $nomDeFamille, ?int $numeroDeMission = null): GameSave
    {
        $this->refuserSiPlafondAtteint($joueur);

        // La mission suivante ne s'ouvre qu'en ayant accompli la précédente
        // (doc 09). Le contrôle est ici, pas seulement dans le formulaire :
        // un POST forgé ne doit pas ouvrir le Sinaï à qui sort du Delta.
        $numero = $numeroDeMission ?? $this->progression->prochaineMission($joueur);

        if (!$this->progression->peutLancer($joueur, $numero)) {
            throw new MissionFermee(\sprintf('La mission %d ne vous est pas encore ouverte : accomplissez d\'abord la précédente.', $numero));
        }

        $mission = $this->missions->get($numero);
        $ville = new City($mission->ville, $mission->difficulte, $mission->tailleDeGrille());

        // La renommée ne repart pas de zéro : la famille arrive avec tout
        // l'acquis de la lignée (doc 13, lot 9.1).
        $partie = GameSave::pourCampagne(
            $joueur,
            new Family($nomDeFamille, $this->lignees->renommeeDeDepart($joueur)),
            $ville,
        );
        $partie->commencerALaMission($mission->numero);

        // Ce que le pharaon précédent lègue : un vrai avantage, proportionnel
        // à ce qu'on a accompli pour lui.
        $partie->recevoirEnLegs($this->legs->debenPour($joueur, $numero));

        return $this->doterEtEnregistrer($partie, $mission->geographie);
    }

    /**
     * @throws PlafondDePartiesAtteint
     */
    public function lancerAventure(User $joueur, string $nomDeFamille, int $difficulte, int $tailleGrille): GameSave
    {
        $this->refuserSiPlafondAtteint($joueur);

        $ville = new City(self::VILLE_DU_MODE_AVENTURE, $difficulte, $tailleGrille);
        $partie = GameSave::pourAventure(
            $joueur,
            new Family($nomDeFamille, $this->lignees->renommeeDeDepart($joueur)),
            $ville,
        );

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

        // Les volontaires que le pharaon a appelés s'installent avant la
        // dotation : c'est leur nombre qui en fixe les vivres, une année de
        // rations pour la population réellement envoyée.
        $ville->accueillir(
            Population::ACTIFS_AU_DEPART,
            Population::ENFANTS_AU_DEPART,
            Population::ANCIENS_AU_DEPART,
        );

        $dotation = DotationRoyale::pour($ville->getDifficulte(), $ville->consommationDeNourriture());
        $ville->crediterRessources($dotation->enRessources());

        // Ce que la maisonnée apporte d'elle-même, par mission déjà servie
        // (doc 13, lot 9.5). **Par-dessus la dotation, jamais à sa place** —
        // et jamais au-delà d'elle : le don du roi doit rester le socle de la
        // partie, pas son appoint.
        $ville->crediterRessources($this->bonus->pour($partie, $dotation->enRessources()));

        // Le legs du pharaon précédent s'ajoute à la dotation, il ne la
        // remplace pas : une première mission et une cinquième démarrent sur
        // le même socle, et c'est ce qui garde chaque mission jouable seule.
        if ($partie->getLegsEnDeben() > 0) {
            $ville->crediterRessources([Ressource::Deben->value => $partie->getLegsEnDeben()]);
        }

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
