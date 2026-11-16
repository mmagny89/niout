<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Sollicitation;
use App\Repository\SollicitationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le monde parle au joueur : une cité offre ou réclame, et la famille répond — ou n'en fait rien
 * (`docs/plan-pnj-palier-a.md` § 7).
 *
 * Les règles, héritées de `QueteDeChantier` et de `Rivaux` :
 *  - **ignorer est une réponse valide** : la sollicitation s'éteint à son échéance, sans coût ;
 *  - **une seule à la fois par cité** ;
 *  - **rien ne quitte les réserves sans accord**, et l'accord se vérifie avant d'agir ;
 *  - **la récompense passe par le plafond commun** : une offre se paie à 110 % du cours — le plancher
 *    d'import d'`AvantageDeNegoce` —, une demande à 140 %, sous les 200 % que la cité consent au plus.
 *    Ni l'une ni l'autre n'ouvre un arbitrage que le commerce ordinaire ne permettait pas.
 *
 * **Le tirage est dérivé de la graine**, comme la conjoncture : une quinzaine donne toujours les mêmes
 * propositions. Elle s'accroche à une quinzaine sur quatre, une fois sur trois.
 */
final readonly class Sollicitations
{
    /** Une fenêtre de proposition, en quinzaines. **Valeur inventée.** */
    public const int FENETRE = 4;

    /** Une fenêtre sur trois en porte une. **Valeur inventée.** */
    private const int FENETRES_AVEC_SOLLICITATION_SUR = 3;

    /** Combien de temps on a pour répondre, en quinzaines. */
    public const int DELAI = 4;

    public const int QUANTITE_MIN = 10;
    public const int QUANTITE_MAX = 30;

    /** Ce que la cité paie ce qu'elle réclame, et ce qu'elle réclame ce qu'elle cède, en centièmes du cours. */
    public const int PRIX_DE_LA_DEMANDE = 140;
    public const int PRIX_DE_L_OFFRE = 110;

    /** Ce qu'une réponse acceptée ajoute à la relation. */
    public const int GAIN_DE_RELATION = 3;

    /** On garde l'historique réglé quarante quinzaines. */
    private const int CONSERVATION = 40;

    public function __construct(
        private Commerce $commerce,
        private EtatsDesCites $cites,
        private SollicitationRepository $sollicitations,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Sollicitation>
     */
    public function enAttente(GameSave $partie): array
    {
        return $this->sollicitations->enAttente($partie);
    }

    /**
     * En fin de quinzaine, avant que le compteur n'avance : éteint ce qui est échu, propose ce que la
     * quinzaine qui s'ouvre porte.
     *
     * @return list<string>
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $cycle = $partie->getCycle() + 1;
        $messages = [];

        foreach ($this->enAttente($partie) as $sollicitation) {
            if ($sollicitation->getEcheance() <= $cycle) {
                $sollicitation->marquer(StatutDeSollicitation::Echue);
            }
        }

        $this->sollicitations->purgerLesReglees($partie, $cycle - self::CONSERVATION);

        if (0 !== $cycle % self::FENETRE) {
            return $messages;
        }

        $enCours = array_map(static fn (Sollicitation $s): string => $s->getCite(), $this->enAttente($partie));

        foreach ($this->commerce->partenairesDe($partie) as $partenaire) {
            $route = $partie->getVille()->routeVers($partenaire->cle);

            if (null === $route || !$route->estOuverte() || \in_array($partenaire->cite(), $enCours, true)) {
                continue;
            }

            $proposee = $this->proposer($partie, $partenaire, $cycle);

            if (null !== $proposee) {
                $this->entityManager->persist($proposee);
                $enCours[] = $partenaire->cite();
                $messages[] = \sprintf('%s : %s', $partenaire->nom, $this->phrase($proposee));
            }
        }

        return $messages;
    }

    /**
     * Ce que la cité propose à cette quinzaine, ou rien. **Pure** : même graine, même résultat.
     */
    public function proposer(GameSave $partie, PartenaireCommercial $partenaire, int $cycle): ?Sollicitation
    {
        $tirage = self::entier($partie->getGraine(), $partenaire->cle, 'sollicitation', $cycle);

        if (0 !== $tirage % self::FENETRES_AVEC_SOLLICITATION_SUR) {
            return null;
        }

        $nature = 0 === intdiv($tirage, 3) % 2 ? NatureDeSollicitation::Offre : NatureDeSollicitation::Demande;
        $choix = array_values(array_filter(
            NatureDeSollicitation::Offre === $nature ? $partenaire->vend : $partenaire->achete,
            static fn (Ressource $r): bool => null !== PrixDuMarche::pour($r) && !$r->estLaMonnaie(),
        ));

        if ([] === $choix) {
            return null;
        }

        $ressource = $choix[intdiv($tirage, 7) % \count($choix)];
        $cours = PrixDuMarche::pour($ressource) ?? 1;
        $quantite = self::QUANTITE_MIN + intdiv($tirage, 13) % (self::QUANTITE_MAX - self::QUANTITE_MIN + 1);
        $prix = max(1, intdiv($cours * (NatureDeSollicitation::Offre === $nature ? self::PRIX_DE_L_OFFRE : self::PRIX_DE_LA_DEMANDE), 100));

        return new Sollicitation($partie, $partenaire->cle, $partenaire->cite(), $nature, $ressource, $quantite, $prix, $cycle, $cycle + self::DELAI);
    }

    /**
     * La famille accepte. Rien ne bouge avant d'avoir vérifié qu'elle le peut.
     *
     * @throws SollicitationImpossible
     */
    public function accepter(GameSave $partie, Sollicitation $sollicitation): void
    {
        $this->verifier($partie, $sollicitation);
        $ville = $partie->getVille();

        if (NatureDeSollicitation::Offre === $sollicitation->getNature()) {
            if (!$ville->debiterRessources([Ressource::Deben->value => $sollicitation->total()])) {
                throw new SollicitationImpossible(\sprintf('Il faut %d deben pour cette offre.', $sollicitation->total()));
            }

            $ville->crediterRessources([$sollicitation->getRessource()->value => $sollicitation->getQuantite()]);
        } else {
            if (!$ville->debiterRessources([$sollicitation->getRessource()->value => $sollicitation->getQuantite()])) {
                throw new SollicitationImpossible(\sprintf('Il vous manque du %s pour répondre.', $sollicitation->getRessource()->libelle()));
            }

            $ville->crediterRessources([Ressource::Deben->value => $sollicitation->total()]);
            $ville->compterUnEchange($sollicitation->total());
        }

        $partenaire = $this->commerce->partenaireDe($partie, $sollicitation->getPartenaire());

        if (null !== $partenaire) {
            $this->cites->obtenir($partie, $partenaire)->gagnerLaRelation(self::GAIN_DE_RELATION);
        }

        $sollicitation->marquer(StatutDeSollicitation::Acceptee);
    }

    /**
     * La famille décline : sans coût.
     *
     * @throws SollicitationImpossible
     */
    public function refuser(GameSave $partie, Sollicitation $sollicitation): void
    {
        $this->verifier($partie, $sollicitation);
        $sollicitation->marquer(StatutDeSollicitation::Refusee);
    }

    public function phrase(Sollicitation $s): string
    {
        return NatureDeSollicitation::Offre === $s->getNature()
            ? \sprintf('cède %d %s à %d deben pièce, jusqu\'à la quinzaine %d.', $s->getQuantite(), $s->getRessource()->libelle(), $s->getPrix(), $s->getEcheance() - 1)
            : \sprintf('réclame %d %s et les paie %d deben pièce, jusqu\'à la quinzaine %d.', $s->getQuantite(), $s->getRessource()->libelle(), $s->getPrix(), $s->getEcheance() - 1);
    }

    /**
     * @throws SollicitationImpossible
     */
    private function verifier(GameSave $partie, Sollicitation $sollicitation): void
    {
        if ($sollicitation->getPartie()->getId() !== $partie->getId()) {
            throw new SollicitationImpossible('Cette proposition n\'est pas la vôtre.');
        }

        if (!$sollicitation->estEnAttente() || $sollicitation->getEcheance() <= $partie->getCycle()) {
            throw new SollicitationImpossible('Cette proposition n\'est plus valable.');
        }

        if (NatureDeSollicitation::Offre === $sollicitation->getNature()) {
            $perdu = $partie->getVille()->surplusRefuse([$sollicitation->getRessource()->value => $sollicitation->getQuantite()]);

            if ([] !== $perdu) {
                throw new SollicitationImpossible('Vos réserves n\'ont pas la place : faites de la place avant d\'accepter.');
            }
        }
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
