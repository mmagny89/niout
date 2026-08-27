<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockDeRessource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockDeRessource>
 */
class StockDeRessourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockDeRessource::class);
    }
}
