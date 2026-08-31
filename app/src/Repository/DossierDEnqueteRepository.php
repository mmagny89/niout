<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DossierDEnquete;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DossierDEnquete>
 */
class DossierDEnqueteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DossierDEnquete::class);
    }
}
