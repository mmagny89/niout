<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSave;
use App\Entity\Npc;
use App\Game\RoleDePnj;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Npc>
 */
class NpcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Npc::class);
    }

    public function trouver(GameSave $partie, RoleDePnj $role, int $identite): ?Npc
    {
        return $this->findOneBy(['partie' => $partie, 'role' => $role, 'identite' => $identite]);
    }

    /**
     * @return list<Npc> ceux que la famille connaît, dans l'ordre de leur rencontre
     */
    public function connus(GameSave $partie, RoleDePnj $role): array
    {
        return $this->findBy(['partie' => $partie, 'role' => $role], ['id' => 'ASC']);
    }
}
