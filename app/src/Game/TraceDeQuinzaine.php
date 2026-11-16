<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\FaitDeMonde;
use App\Entity\GameSave;
use App\Entity\InstantaneDeQuinzaine;
use App\Repository\FaitDeMondeRepository;
use App\Repository\InstantaneDeQuinzaineRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ce que le jeu garde d'une quinzaine pour pouvoir la rejouer
 * (`docs/plan-pnj-ville-vivante.md` § 7) : **la photographie du début, et les
 * faits** — jamais une scène, que la chorégraphie recalcule.
 *
 * **Les trois dernières quinzaines, pas davantage.** Rejouer une quinzaine plus
 * ancienne n'est pas possible, et le contrat le dit : la purge en fait partie.
 *
 * Écrit dans le même `flush` que le reste du cycle (`PassageDeCycle`), jamais
 * seul. Réécrire une quinzaine déjà tracée la remplace : le cycle est rejouable,
 * la trace ne double pas.
 *
 * @phpstan-import-type Photo from PhotoDeLaVille
 * @phpstan-import-type Fait from DifferenceDeQuinzaine
 */
final readonly class TraceDeQuinzaine
{
    public const int QUINZAINES_GARDEES = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InstantaneDeQuinzaineRepository $instantanes,
        private FaitDeMondeRepository $faits,
    ) {
    }

    /**
     * Range la quinzaine qui vient de passer : la photographie d'avant, et ce que
     * la comparaison avec celle d'après en dit. **Ne flushe pas.**.
     *
     * @param Photo $avant
     * @param Photo $apres
     */
    public function enregistrer(GameSave $partie, array $avant, array $apres): void
    {
        $cycle = $avant['cycle'];

        $existant = $this->instantanes->deLaQuinzaine($partie, $cycle);

        if (null === $existant) {
            $this->entityManager->persist(new InstantaneDeQuinzaine($partie, $cycle, $avant));
        } else {
            $existant->remplacer($avant);
        }

        $this->faits->effacerLaQuinzaine($partie, $cycle);

        foreach (DifferenceDeQuinzaine::faits($avant, $apres) as $fait) {
            $this->entityManager->persist(new FaitDeMonde(
                $partie,
                $cycle,
                $fait['type'],
                $fait['lieu'],
                $fait['acteur'],
                $fait['quantite'],
                $fait['details'],
            ));
        }

        $this->instantanes->purgerAvant($partie, $cycle - self::QUINZAINES_GARDEES + 1);
        $this->faits->purgerAvant($partie, $cycle - self::QUINZAINES_GARDEES + 1);
    }

    /**
     * La quinzaine qui vient de passer, si la trace en garde une : son cycle, sa
     * photographie, ses faits. Null sur une partie qui n'a pas encore vécu de
     * quinzaine — ou dont le jeu est antérieur à la trace.
     *
     * @return array{cycle: int, photo: Photo, faits: list<Fait>}|null
     */
    public function derniere(GameSave $partie): ?array
    {
        $instantane = $this->instantanes->dernier($partie);

        if (null === $instantane) {
            return null;
        }

        /** @var Photo $photo */
        $photo = $instantane->getDonnees();

        return [
            'cycle' => $instantane->getCycle(),
            'photo' => $photo,
            'faits' => array_map(
                static fn (FaitDeMonde $fait): array => [
                    'type' => $fait->getType(),
                    'lieu' => $fait->getLieu(),
                    'acteur' => $fait->getActeur(),
                    'quantite' => $fait->getQuantite(),
                    'details' => $fait->getDetails(),
                ],
                $this->faits->deLaQuinzaine($partie, $instantane->getCycle()),
            ),
        ];
    }
}
