<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaveurDivine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaveurDivine>
 */
class FaveurDivineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaveurDivine::class);
    }
}
