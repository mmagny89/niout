<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSave;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSave>
 */
class GameSaveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSave::class);
    }

    /**
     * Parties d'un joueur, de la plus récemment ouverte à la plus ancienne :
     * c'est presque toujours celle qu'il vient reprendre.
     *
     * @return GameSave[]
     */
    public function findPourJoueur(User $joueur): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.joueur = :joueur')
            ->setParameter('joueur', $joueur)
            ->orderBy('p.lastOpenedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Sert à faire respecter le plafond de GameSave::MAX_PAR_COMPTE.
     */
    public function compterPourJoueur(User $joueur): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.joueur = :joueur')
            ->setParameter('joueur', $joueur)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Vrai quand le joueur a atteint son plafond de parties simultanées.
     */
    public function plafondAtteintPour(User $joueur): bool
    {
        return $this->compterPourJoueur($joueur) >= GameSave::MAX_PAR_COMPTE;
    }
}
