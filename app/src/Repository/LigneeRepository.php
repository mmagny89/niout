<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lignee;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lignee>
 */
class LigneeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lignee::class);
    }

    public function findPourJoueur(User $joueur): ?Lignee
    {
        return $this->findOneBy(['joueur' => $joueur]);
    }
}
