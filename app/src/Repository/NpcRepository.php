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
     * Comme `trouver()`, mais **voit aussi ceux qui attendent leur flush** : deux gestes d'une même quinzaine
     * (deux convois pillés par la même bande) ne doivent pas créer deux fois la même personne — la contrainte
     * d'unicité les refuserait au flush, et une personne dédoublée perdrait sa mémoire.
     */
    public function trouverOuEnAttente(GameSave $partie, RoleDePnj $role, int $identite): ?Npc
    {
        foreach ($this->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entite) {
            if ($entite instanceof Npc
                && $entite->getRole() === $role
                && $entite->getIdentite() === $identite
                && $entite->getPartie() === $partie) {
                return $entite;
            }
        }

        return $this->trouver($partie, $role, $identite);
    }

    /**
     * @return list<Npc> ceux que la famille connaît, dans l'ordre de leur rencontre
     */
    public function connus(GameSave $partie, RoleDePnj $role): array
    {
        return $this->findBy(['partie' => $partie, 'role' => $role], ['id' => 'ASC']);
    }
}
