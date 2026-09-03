<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSave>
 */
class GameSaveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSave::class);
    }

    /**
     * Parties d'un joueur, de la plus récemment ouverte à la plus ancienne :
     * c'est presque toujours celle qu'il vient reprendre.
     *
     * @return GameSave[]
     */
    public function findPourJoueur(User $joueur): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.joueur = :joueur')
            ->setParameter('joueur', $joueur)
            ->orderBy('p.lastOpenedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Sert à faire respecter le plafond de GameSave::MAX_PAR_COMPTE.
     */
    public function compterPourJoueur(User $joueur): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.joueur = :joueur')
            ->setParameter('joueur', $joueur)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Ses parties **encore en cours** — celles qui occupent réellement une
     * place.
     *
     * **Défaut réel, payé** : le plafond comptait toutes les parties, closes
     * comprises. Un joueur qui accomplissait cinq missions ne pouvait plus en
     * lancer une sixième, ce qui rendait la campagne de dix missions
     * infinissable sans supprimer des parties. Le docblock de
     * `GameSave::MAX_PAR_COMPTE` disait pourtant « parties **simultanées** » :
     * c'était l'implémentation qui divergeait de l'intention.
     *
     * **Une partie close ne se supprime jamais** (doc 02, décision actée) :
     * échouée comme achevée, elle reste consultable. Elle ne doit donc pas
     * occuper une place, sans quoi jouer longtemps finirait par interdire de
     * jouer.
     */
    public function compterEnCoursPourJoueur(User $joueur): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.joueur = :joueur')
            ->andWhere('p.statut = :enCours')
            ->setParameter('joueur', $joueur)
            ->setParameter('enCours', StatutDePartie::EnCours)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Vrai quand le joueur a atteint son plafond de parties **simultanées**.
     */
    public function plafondAtteintPour(User $joueur): bool
    {
        return $this->compterEnCoursPourJoueur($joueur) >= GameSave::MAX_PAR_COMPTE;
    }
}
