<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\QueteDeChantier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QueteDeChantier>
 */
class QueteDeChantierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueteDeChantier::class);
    }
}
