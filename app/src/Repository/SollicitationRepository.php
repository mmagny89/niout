<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSave;
use App\Entity\Sollicitation;
use App\Game\StatutDeSollicitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sollicitation>
 */
class SollicitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sollicitation::class);
    }

    /**
     * @return list<Sollicitation>
     */
    public function enAttente(GameSave $partie): array
    {
        return $this->findBy(['partie' => $partie, 'statut' => StatutDeSollicitation::Proposee], ['id' => 'ASC']);
    }

    /**
     * Ce qui est réglé depuis longtemps ne sert plus à rien : on le purge au-delà de `$avant`.
     */
    public function purgerLesReglees(GameSave $partie, int $avant): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.partie = :partie')
            ->andWhere('s.statut != :attente')
            ->andWhere('s.quinzaine < :avant')
            ->setParameter('partie', $partie)
            ->setParameter('attente', StatutDeSollicitation::Proposee)
            ->setParameter('avant', $avant)
            ->getQuery()
            ->execute();
    }
}
