<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConsigneDeFabrication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsigneDeFabrication>
 */
class ConsigneDeFabricationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsigneDeFabrication::class);
    }
}
