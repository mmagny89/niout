<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Faire venir des habitants dans la ville (doc 13, décision de la joueuse).
 *
 * Le pharaon a envoyé les premiers volontaires ; après quoi c'est au joueur
 * d'aller chercher du monde. Deux choses le gouvernent, et le doc 13 les tient
 * toutes deux pour l'effet central de la renommée :
 *
 * - **La renommée fixe le prix.** Une famille inconnue doit payer cher pour
 *   convaincre une maisonnée de traverser le pays ; une famille illustre n'a
 *   presque rien à débourser (`PalierDeRenommee::coutDAppel()`).
 * - **Le logement fixe la limite.** On ne fait pas venir des gens qu'on ne
 *   peut pas loger : il faut bâtir le Quartier d'habitation avant d'espérer
 *   grandir.
 *
 * À partir du palier « Respectée », des maisonnées s'installent en plus
 * d'elles-mêmes, sans qu'on les appelle ni qu'on les paie — c'est la
 * « migration spontanée » du doc 13, résolue une fois l'an par `Demographie`.
 */
final readonly class AppelDHabitants
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Le prix courant d'une maisonnée, pour que l'écran l'annonce avant que le
     * joueur ne s'engage.
     */
    public function cout(GameSave $partie): int
    {
        return $partie->getFamille()->palier()->coutDAppel();
    }

    /**
     * Fait venir une maisonnée. Renvoie ce qu'elle amène, pour que le joueur
     * sache s'il a gagné des bras ou des bouches.
     *
     * @return array{actifs: int, inactifs: int}
     *
     * @throws AppelImpossible
     */
    public function appeler(GameSave $partie): array
    {
        $ville = $partie->getVille();

        if ($ville->manqueDeLogements()) {
            throw new AppelImpossible('Vos maisons sont pleines. Montez le Quartier d\'habitation avant de faire venir du monde.');
        }

        $cout = $this->cout($partie);

        if (!$ville->debiterRessources([Ressource::Deben->value => $cout])) {
            throw new AppelImpossible(\sprintf('Il vous faut %d deben pour convaincre une maisonnée de s\'installer. Votre renommée est %s.', $cout, mb_strtolower($partie->getFamille()->palierDeRenommee())));
        }

        $maisonnee = Population::maisonneeQuiArrive($this->hasard);
        $ville->accueillir($maisonnee['actifs'], $maisonnee['inactifs'], 0);

        $this->entityManager->flush();

        return $maisonnee;
    }
}
