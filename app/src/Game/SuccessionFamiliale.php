<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Le passage d'une génération à la suivante (doc 13, lot 11.5).
 *
 * **Reporté depuis la Phase 9, et pour une raison qui tenait** : une génération
 * dure soixante cycles, quand une mission de campagne les dépasse rarement. Le
 * mode Aventure, qui traverse plus de deux cents cycles, est le premier où le
 * lot se déclenche vraiment.
 *
 * **Ce qui persiste** : la renommée, les contacts, la faveur divine, la ville
 * entière. **Ce qui se renouvelle** : le trait actif et le nom du chef de
 * famille. Rien ne se perd — c'est la ville qui compte, pas la personne.
 *
 * **Rien ne se persiste des héritiers proposés**, seulement la graine qui les
 * décide : deux visites du même écran montrent les mêmes, sans qu'aucune table
 * ne les porte.
 */
final readonly class SuccessionFamiliale
{
    /**
     * Ce que dure une génération, en cycles (doc 13 : soixante, plus ou moins
     * vingt).
     */
    public const int DUREE_DUNE_GENERATION = 60;
    public const int ECART_DE_GENERATION = 20;

    /**
     * Combien d'héritiers se présentent. Le doc 03 en donne deux ou trois pour
     * une offre d'emploi ; la succession suit la même main.
     */
    public const int HERITIERS_MINIMUM = 2;
    public const int HERITIERS_MAXIMUM = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Le cycle où la génération en cours passe la main. Il se déduit de la
     * génération : la première dure la durée pleine, chacune ensuite décale
     * d'autant, l'écart venant de la graine plutôt que d'un tirage refait à
     * chaque appel.
     */
    public function cycleDeLaProchaineSuccession(GameSave $partie): int
    {
        $famille = $partie->getFamille();
        $ecart = ($famille->getGeneration() * 7) % (2 * self::ECART_DE_GENERATION + 1) - self::ECART_DE_GENERATION;

        return $famille->getGeneration() * self::DUREE_DUNE_GENERATION + $ecart;
    }

    /**
     * Vrai quand la génération a fait son temps et qu'un héritier doit
     * prendre la suite.
     *
     * **Le mode Aventure seul** : une mission de campagne dure une trentaine
     * de cycles, et changer de chef au milieu d'une commande royale n'aurait
     * pas de sens.
     */
    public function estOuverte(GameSave $partie): bool
    {
        if ($partie->estCampagne() || !$partie->estEnCours()) {
            return false;
        }

        return $partie->getCycle() >= $this->cycleDeLaProchaineSuccession($partie);
    }

    /**
     * Les héritiers qui se présentent, déduits de la graine gardée sur la
     * famille. La graine se pose à la première consultation et ne bouge plus
     * jusqu'au choix : on ne change pas d'héritiers entre deux clics.
     *
     * @return list<Heritier>
     */
    public function heritiers(GameSave $partie): array
    {
        if (!$this->estOuverte($partie)) {
            return [];
        }

        $famille = $partie->getFamille();

        if (0 === $famille->getGraineDesHeritiers()) {
            $famille->preparerUneSuccession($this->hasard->getInt(1, 1_000_000));
            $this->entityManager->flush();
        }

        $graine = $famille->getGraineDesHeritiers();
        $combien = self::HERITIERS_MINIMUM + $graine % (self::HERITIERS_MAXIMUM - self::HERITIERS_MINIMUM + 1);
        $traits = TraitDeCandidat::cases();
        $heritiers = [];

        foreach (range(0, $combien - 1) as $rang) {
            $sien = $graine + $rang * 977;
            $combienDeTraits = $sien % 3 > 0 ? 1 : 0;
            $lesSiens = [];

            if ($combienDeTraits > 0) {
                $lesSiens[] = $traits[$sien % \count($traits)];
            }

            $heritiers[] = new Heritier(PrenomEgyptien::selonLaGraine($sien), $lesSiens);
        }

        return $heritiers;
    }

    /**
     * Un héritier prend la suite.
     *
     * @throws SuccessionImpossible
     */
    public function choisir(GameSave $partie, int $rang): Heritier
    {
        $heritiers = $this->heritiers($partie);

        if (!isset($heritiers[$rang])) {
            throw new SuccessionImpossible('Cet héritier ne se présente pas.');
        }

        $heritier = $heritiers[$rang];
        $partie->getFamille()->accueillirUnHeritier($heritier->prenom, $heritier->traits[0] ?? null);
        $this->entityManager->flush();

        return $heritier;
    }
}
