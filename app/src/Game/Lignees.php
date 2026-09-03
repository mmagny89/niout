<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Lignee;
use App\Entity\User;
use App\Repository\LigneeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * L'accès à la lignée d'un joueur, et les deux seuls gestes qu'on lui fait
 * subir : la lire au lancement d'une partie, la relever à la fin d'une mission
 * (doc 13).
 *
 * **Créée paresseusement.** Un compte qui n'a jamais joué n'a pas de lignée :
 * la fabriquer à l'inscription obligerait à la rattraper pour tous les comptes
 * existants, et à la maintenir dans le tunnel d'inscription, pour un objet qui
 * ne sert qu'au premier lancement.
 */
final readonly class Lignees
{
    public function __construct(
        private LigneeRepository $lignees,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * La lignée du joueur, créée si c'est sa première. **Non flushée** : elle
     * l'est par l'appelant, avec la partie qu'elle sert.
     */
    public function pour(User $joueur): Lignee
    {
        $lignee = $this->lignees->findPourJoueur($joueur);

        if (null === $lignee) {
            $lignee = new Lignee($joueur);
            $this->entityManager->persist($lignee);
        }

        return $lignee;
    }

    /**
     * Ce avec quoi une nouvelle partie démarre : tout l'acquis, sans
     * proportion ni plafond. C'est l'inverse exact de l'ancien legs de
     * renommée, qui repartait de zéro et n'ajoutait que quatre points.
     */
    public function renommeeDeDepart(User $joueur): int
    {
        return $this->pour($joueur)->getRenommeeAcquise();
    }

    /**
     * Verse à la lignée la renommée que la famille a en ce moment.
     *
     * **Les deux modes l'alimentent** (arbitrage 11.0) : la campagne à
     * l'achèvement d'une mission, l'Aventure à chaque fin de règne. La
     * renommée appartient à la famille, pas au mode — l'asymétrie d'avant ne se
     * justifiait par aucun document, et l'Aventure n'avait simplement aucun
     * jalon où la verser tant que les règnes n'existaient pas.
     *
     * **C'est l'appelant qui décide du jalon, jamais cette méthode** : elle ne
     * sait pas ce qui vient de s'achever, seulement qu'il faut relever
     * l'acquis. L'acquis ne peut que monter, ici comme ailleurs.
     */
    public function encaisser(GameSave $partie): void
    {
        $this->pour($partie->getJoueur())->relever($partie->getFamille()->getRenommee());
    }
}
