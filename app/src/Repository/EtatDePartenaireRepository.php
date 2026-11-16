<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EtatDePartenaire;
use App\Entity\GameSave;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtatDePartenaire>
 */
class EtatDePartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtatDePartenaire::class);
    }

    /**
     * @return list<EtatDePartenaire>
     */
    public function deLaPartie(GameSave $partie): array
    {
        return $this->findBy(['partie' => $partie]);
    }
}
