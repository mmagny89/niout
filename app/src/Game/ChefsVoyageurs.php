<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Npc;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Un chef qui part va quelque part (`docs/plan-pnj-palier-b.md` § 5.2).
 *
 * Avant, un départ était une disparition. Maintenant le chef **rejoint une cité** du monde, y fait
 * quelque chose, et peut **revenir comme candidat**, aguerri.
 *
 *  - **Il part de lui-même** (`DepartsNaturels`, selon ses traits et le mécontentement). Une cité ne
 *    débauche jamais : un PNJ qui détruirait une capacité du joueur serait la faute à éviter.
 *  - **Le foyer suit**, comme avant : un départ n'ajoute ni ne retire de population de plus.
 *  - **Un Négociateur parti** élève la relation de la famille avec sa cité — par le canal existant,
 *    `EtatDePartenaire::gagnerLaRelation`, jamais un facteur de plus dans `AvantageDeNegoce`. Borné.
 *  - **Il revient** à une annonce du même bâtiment, au plus tôt `DELAI_DE_RETOUR` quinzaines après son
 *    départ, une fois sur deux : même prénom, même spécialité, plus compétent — dans les bornes du
 *    générateur (20 à 100). Il ne revient qu'une fois.
 *
 * **La destination se déduit de la graine** (identité du chef) : rejouable. Seul ce que le joueur a
 * touché — un chef qu'il avait embauché, qui est donc parti — est persisté, comme tout `Npc`.
 *
 * Le Logisticien parti, qui pourrait raccourcir un trajet vers sa cité, n'est pas écrit : il touche au
 * calcul des trajets, qui mérite son propre cadrage.
 */
final readonly class ChefsVoyageurs
{
    /** Plus tôt, un chef parti n'a rien vu qui le change. **Valeur inventée.** */
    public const int DELAI_DE_RETOUR = 12;

    /** Ce qu'il rapporte de ses voyages, en points de compétence. **Valeur inventée.** */
    public const int COMPETENCE_RAPPORTEE = 8;

    /** La relation qu'un Négociateur gagne à la cité par quinzaine, et ce qu'il peut gagner en tout. **Valeurs inventées.** */
    public const int RELATION_PAR_QUINZAINE = 2;
    public const int RELATION_GAGNEE_MAX = 10;

    public function __construct(
        private Commerce $commerce,
        private EtatsDesCites $cites,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Un chef quitte son poste : où va-t-il ? `null` si le monde ne lui offre aucune cité.
     */
    public function emmener(GameSave $partie, Employee $chef): ?PartenaireCommercial
    {
        $cites = $this->commerce->partenairesDe($partie);

        if ([] === $cites) {
            return null;
        }

        $cycle = $partie->getCycle();
        $identite = self::entier($partie->getGraine(), 'voyageur', $cycle, $chef->getId() ?? 0, $chef->getCompetence());
        $destination = $cites[$identite % \count($cites)];

        $nom = $chef->getPrenom() ?? \sprintf('Un chef de l\'%s', mb_strtolower($chef->getType()->libelle()));
        $voyageur = new Npc($partie, $identite, RoleDePnj::ChefVoyageur, $nom, $chef->traits()[0] ?? TraitDeCandidat::Fidele);
        $voyageur->definirLeProfil([
            'candidat' => (new Candidat(
                competence: $chef->getCompetence(),
                salaire: $chef->getSalaire(),
                ancienneteProbable: $chef->getAncienneteProbable(),
                traits: $chef->traits(),
                specialite: $chef->getSpecialite(),
                actifsAmenes: $chef->getActifsAmenes(),
                inactifsAmenes: $chef->getInactifsAmenes(),
                prenom: $chef->getPrenom(),
            ))->enTableau(),
            'batiment' => $chef->getType()->value,
            'destination' => $destination->cle,
            'depart' => $cycle,
            'gagne' => 0,
            'revenu' => false,
        ]);
        $voyageur->noter($cycle, 'part', $destination->cle);
        $this->entityManager->persist($voyageur);

        return $destination;
    }

    /**
     * Chaque quinzaine, ceux qui travaillent ailleurs y font ce qu'ils savent faire.
     */
    public function avancerDUnCycle(GameSave $partie): void
    {
        foreach ($this->parLes($partie) as $voyageur) {
            $profil = $voyageur->getProfil();

            if (true === ($profil['revenu'] ?? false)) {
                continue;
            }

            $candidat = Candidat::depuisTableau(\is_array($profil['candidat'] ?? null) ? $profil['candidat'] : []);

            if (SpecialiteDeChef::EntrepotNegociateur !== $candidat->specialite || (int) ($profil['gagne'] ?? 0) >= self::RELATION_GAGNEE_MAX) {
                continue;
            }

            $cite = $this->commerce->partenaireDe($partie, (string) ($profil['destination'] ?? ''));

            if (null === $cite) {
                continue;
            }

            $gain = min(self::RELATION_PAR_QUINZAINE, self::RELATION_GAGNEE_MAX - (int) ($profil['gagne'] ?? 0));
            $this->cites->obtenir($partie, $cite)->gagnerLaRelation($gain);
            $voyageur->definirLeProfil(['gagne' => (int) ($profil['gagne'] ?? 0) + $gain] + $profil);
        }
    }

    /**
     * Une annonce se prépare pour ce bâtiment : un chef parti peut-il s'y présenter ? Il remplace alors
     * l'un des candidats tirés, **plus compétent de ce qu'il a vu**.
     *
     * @param list<Candidat> $candidats
     *
     * @return list<Candidat>
     */
    public function auRetour(GameSave $partie, TypeDeBatiment $batiment, array $candidats): array
    {
        if ([] === $candidats) {
            return $candidats;
        }

        $cycle = $partie->getCycle();

        foreach ($this->parLes($partie) as $voyageur) {
            $profil = $voyageur->getProfil();

            if (($profil['batiment'] ?? null) !== $batiment->value
                || [] === ($profil['candidat'] ?? [])
                || !$voyageur->peutParaitre($cycle)
                || true === ($profil['revenu'] ?? false)
                || $cycle < (int) ($profil['depart'] ?? $cycle) + self::DELAI_DE_RETOUR
                || 0 !== self::entier($partie->getGraine(), 'retour', $voyageur->getIdentite(), $cycle) % 2) {
                continue;
            }

            $ancien = Candidat::depuisTableau(\is_array($profil['candidat'] ?? null) ? $profil['candidat'] : []);
            $aguerri = new Candidat(
                competence: min(GenerateurDeCandidat::COMPETENCE_MAX, $ancien->competence + self::COMPETENCE_RAPPORTEE),
                salaire: $ancien->salaire,
                ancienneteProbable: $ancien->ancienneteProbable,
                traits: $ancien->traits,
                specialite: $ancien->specialite,
                actifsAmenes: $ancien->actifsAmenes,
                inactifsAmenes: $ancien->inactifsAmenes,
                prenom: $ancien->prenom,
            );

            $voyageur->definirLeProfil(['revenu' => true] + $profil);
            $voyageur->noter($cycle, 'rentre', (string) ($profil['destination'] ?? ''));

            $candidats[\count($candidats) - 1] = $aguerri;

            return $candidats;
        }

        return $candidats;
    }

    /**
     * @return list<Npc>
     */
    private function parLes(GameSave $partie): array
    {
        return $this->entityManager->getRepository(Npc::class)->findBy(['partie' => $partie, 'role' => RoleDePnj::ChefVoyageur], ['id' => 'ASC']);
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
