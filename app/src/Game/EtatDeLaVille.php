<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce qui va et ce qui ne va pas, en une liste de signaux.
 *
 * **Le joueur ne doit pas changer d'écran pour savoir où en est sa ville.**
 * La fièvre, le mécontentement et la famine ne se lisaient que dans la ville,
 * alors qu'on passe des quinzaines entières sur la carte à explorer et à
 * exploiter — on découvrait la maladie en revenant, plusieurs quinzaines trop
 * tard. Les deux écrans lisent donc la même liste.
 *
 * **Le bon compte autant que le mauvais** (décision de la joueuse) : une fête
 * qui passe, une crue généreuse, des dieux acquis, un renom qui attire — ce
 * sont des moments à saisir, et un écran qui ne signalerait que les ennuis
 * transformerait le jeu en liste de pannes. Chaque signal porte donc son
 * `ton`, et l'écran les range par ton plutôt que de les mélanger.
 *
 * **Chaque signal nomme la cause et le geste.** Un diagnostic sans remède se
 * subit ; c'est la règle des alertes de la Résidence, appliquée ici à tout ce
 * que la ville a d'notable.
 */
final readonly class EtatDeLaVille
{
    public function __construct(
        private GeographieDeLaPartie $geographies,
    ) {
    }

    /**
     * En dessous de combien de quinzaines de vivres la réserve inquiète.
     *
     * Calé sur `Subsistance::SEUIL_DE_FAMINE` : c'est le nombre de quinzaines
     * de disette qui mène au mécontentement, donc le délai dont le joueur a
     * besoin pour réagir avant que la spirale ne s'amorce.
     */
    public const int QUINZAINES_DE_VIVRES_INQUIETANTES = Subsistance::SEUIL_DE_FAMINE;

    /**
     * Tout ce que la ville a de notable, le bon comme le mauvais.
     *
     * @return list<array{ton: string, titre: string, detail: string}>
     */
    public function signaux(GameSave $partie): array
    {
        return [...$this->ennuis($partie), ...$this->bonnesNouvelles($partie)];
    }

    /**
     * @return list<array{ton: string, titre: string, detail: string}>
     */
    public function ennuis(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $signaux = [];

        if ($ville->estFrappeeParUneEpidemie()) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('La fièvre couche %d bras', $ville->malades()),
                'detail' => \sprintf(
                    'Encore %d quinzaine%s. Nul n\'en meurt, mais tout produit moins. Une offrande à Sekhmet, dont les prêtres soignent, en abrégerait le cours.',
                    $ville->getQuinzainesDepidemie(),
                    $ville->getQuinzainesDepidemie() > 1 ? 's' : '',
                ),
            ];
        }

        $famine = $partie->getQuinzainesDeFamine();

        if ($famine > 0) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('On ne mange pas à sa faim depuis %d quinzaine%s', $famine, $famine > 1 ? 's' : ''),
                'detail' => \sprintf(
                    'Le mécontentement s\'installe à %d, la partie se perd à %d — c\'est la seule façon de perdre. Semez, pêchez, achetez.',
                    Subsistance::SEUIL_DE_FAMINE,
                    Subsistance::SEUIL_DECHEC,
                ),
            ];
        }

        $colere = $partie->getQuinzainesDeMecontentement();

        if ($colere > 0) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('La ville est mécontente depuis %d quinzaine%s', $colere, $colere > 1 ? 's' : ''),
                'detail' => 'On n\'y mange pas à sa faim, ou l\'on n\'y est pas payé. La production baisse, les départs s\'accélèrent. La colère retombe d\'un cran par quinzaine, aussi lentement qu\'elle est montée.',
            ];
        }

        $autonomie = $this->autonomieEnVivres($partie);

        if (null !== $autonomie && $autonomie < self::QUINZAINES_DE_VIVRES_INQUIETANTES) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('Vos vivres ne tiennent que %d quinzaine%s', $autonomie, $autonomie > 1 ? 's' : ''),
                'detail' => 'Semez, jetez les filets, ou achetez avant que la disette ne commence à compter.',
            ];
        }

        if ($ville->manqueDeLogements()) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => 'Vos maisons sont pleines',
                'detail' => \sprintf(
                    'Personne de plus ne s\'installera — ni une maisonnée appelée, ni celle d\'un chef —, et aucun enfant ne naîtra tant qu\'il n\'y aura pas de place. %s : chaque niveau loge %d maisonnées.',
                    $ville->possede(TypeDeBatiment::QuartierDHabitation)
                        ? 'Montez le Quartier d\'habitation'
                        : 'Dressez un Quartier d\'habitation',
                    Population::FAMILLES_PAR_NIVEAU_DE_QUARTIER,
                ),
            ];
        }

        $bilan = Effectifs::bilan($ville, $partie->getCycle());

        if ($bilan['manquants'] > 0) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('Il vous manque %d bras', $bilan['manquants']),
                'detail' => 'Les bâtiments sont servis avant le territoire, et chacun tourne à proportion de ce qu\'il a reçu. Faites venir du monde, ou renvoyez un chef dont vous ne pouvez pas tenir le bâtiment.',
            ];
        } elseif ($bilan['oisifs'] > 0) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('%d bras sont sans ouvrage', $bilan['oisifs']),
                'detail' => 'Ils mangent et ne produisent rien. Embauchez un chef quelque part — c\'est le chef qui recrute —, semez un champ ou ouvrez une carrière.',
            ];
        }

        if ($ville->vivresPresqueSatures() || $ville->materiauxPresqueSatures()) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => 'Vos réserves débordent bientôt',
                'detail' => 'Ce qui rentrerait au-delà du plafond se perd, sans que rien ne le dise sur le moment. Écoulez au Marché, offrez au Temple, ou agrandissez le Grenier et l\'Entrepôt.',
            ];
        }

        $rival = $ville->getRival();

        if (null !== $rival) {
            $signaux[] = [
                'ton' => 'mauvais',
                'titre' => \sprintf('%s vous dispute une route', $rival->getNom()),
                'detail' => 'Il prend une part de ce qui passe et s\'en ira de lui-même. Vous pouvez aussi le payer, ou chercher sur quoi il tient.',
            ];
        }

        return $signaux;
    }

    /**
     * @return list<array{ton: string, titre: string, detail: string}>
     */
    public function bonnesNouvelles(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $signaux = [];
        $fete = $partie->feteEnCours();

        if (null !== $fete) {
            $signaux[] = [
                'ton' => 'bon',
                'titre' => $fete->libelle(),
                'detail' => \sprintf(
                    '%s Toute offrande à %s vaut %d points de faveur de plus tant qu\'elle dure.',
                    $fete->description(),
                    $fete->divinite()->libelle(),
                    Offrandes::POINTS_DE_FETE,
                ),
            ];
        }

        $geographie = $this->geographies->pour($partie);

        // **Pas de crue là où il n'y a pas de fleuve.** Annoncer une crue
        // généreuse au Sinaï promettait une moisson qu'aucun limon ne
        // viendrait nourrir.
        if ($geographie->connaitLaCrue() && QualiteDeCrue::Forte === $partie->getCrue()) {
            $signaux[] = [
                'ton' => 'bon',
                'titre' => 'La crue est forte cette année',
                'detail' => $partie->getCrue()->presage(),
            ];
        }

        $acquis = [];

        foreach ($ville->divinitesHonorees() as $divinite) {
            // Un dieu sans prise sur cette région n'est pas un atout, quelle
            // que soit sa faveur : le compter parmi les acquis serait annoncer
            // un effet qui ne se produit pas.
            if ($ville->palierDe($divinite)->estAuDessusDuNeutre() && $divinite->agitDans($geographie)) {
                $acquis[] = $divinite->libelle();
            }
        }

        if ([] !== $acquis) {
            $signaux[] = [
                'ton' => 'bon',
                'titre' => \sprintf('%d divinité%s vous %s acquise%s', \count($acquis), \count($acquis) > 1 ? 's' : '', \count($acquis) > 1 ? 'sont' : 'est', \count($acquis) > 1 ? 's' : ''),
                'detail' => implode(', ', $acquis).'. Leurs effets courent tant que vous les entretenez ; cinq quinzaines sans offrande, et la faveur redescend.',
            ];
        }

        $palier = $partie->getFamille()->palier();

        if ($palier->chanceDeMigrationSpontanee() > 0) {
            $signaux[] = [
                'ton' => 'bon',
                'titre' => \sprintf('Votre famille est %s', mb_strtolower($palier->libelle())),
                'detail' => $palier->attractivite(),
            ];
        }

        return $signaux;
    }

    /**
     * Combien de quinzaines la ville tient sans rien récolter — null quand
     * elle ne mange rien, ce qui n'arrive qu'à une ville vide.
     */
    public function autonomieEnVivres(GameSave $partie): ?int
    {
        $consommation = $partie->getVille()->consommationDeNourriture();

        return $consommation > 0 ? intdiv($partie->getVille()->getNourriture(), $consommation) : null;
    }
}
