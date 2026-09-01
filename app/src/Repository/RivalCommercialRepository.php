<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RivalCommercial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RivalCommercial>
 */
class RivalCommercialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RivalCommercial::class);
    }
}
