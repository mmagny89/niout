<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Le fil rouge d'une mission, en trois actes (doc 09, doc 10).
 *
 * **Les dix missions ont le leur** (lot 8.4), sur la structure éprouvée à la
 * mission 1 : une tablette du roi, un obstacle local que l'enquête met au
 * jour, une stèle qu'on relit avant de la dresser.
 *
 * **Une contrainte que le jeu impose à l'écriture** : la clé de lecture
 * repart de quatre signes à chaque mission. L'inscription d'ouverture ne peut
 * donc employer que ceux-là — eau, homme, maison, marche —, et c'est ce qui
 * donne à ces tablettes leur ton lapidaire. La stèle finale, elle, en compte
 * cinq : à la fin d'une mission, les scribes ont appris.
 *
 * **L'acte courant se déduit, il ne se stocke pas.** Il découle de ce qui est
 * déjà vrai — l'inscription d'ouverture est-elle lue, l'enquête principale
 * est-elle résolue, la stèle finale est-elle relue. Une colonne « acte en
 * cours » à tenir à jour finirait par diverger de ces trois faits, et c'est
 * exactement le genre de désynchronisation qu'on ne voit qu'en partie.
 *
 * Le contexte : Ahmôsis Ier vient de chasser les Hyksôs, et charge la famille
 * de rouvrir le commerce du Delta. Les troubles résiduels de cette période
 * sont ce que l'enquête met au jour.
 */
final readonly class FilRouge
{
    /**
     * Un fil rouge court sur toute mission de campagne. Le mode Aventure n'en
     * a pas : ses inscriptions redeviennent alors ordinaires plutôt que de
     * rester à jamais inaccessibles.
     */
    public static function court(GameSave $partie): bool
    {
        return $partie->estCampagne()
            && null !== $partie->getMission()
            && null !== self::ouverture($partie->getMission());
    }

    /**
     * La tablette que le roi fait porter avec sa commande. Trois signes, tous
     * connus d'emblée : le tutoriel du système ne peut pas demander un
     * bâtiment.
     */
    public static function ouverture(int $mission): ?Inscription
    {
        return match ($mission) {
            1 => Inscription::CommandeDAhmosis,
            2 => Inscription::OuvertureDeSai,
            3 => Inscription::OuvertureDeMersa,
            4 => Inscription::OuvertureDeMegiddo,
            5 => Inscription::OuvertureDeMalkata,
            6 => Inscription::OuvertureDAkhetaton,
            7 => Inscription::OuvertureDElephantine,
            8 => Inscription::OuvertureDeShedet,
            9 => Inscription::OuvertureDuOuadi,
            10 => Inscription::OuvertureDuSinai,
            default => null,
        };
    }

    /**
     * La stèle qu'on grave une fois l'affaire close, et qu'il faut relire
     * avant de la dresser. Cinq signes : à la fin d'une mission, les scribes
     * ont appris.
     */
    public static function stele(int $mission): ?Inscription
    {
        return match ($mission) {
            1 => Inscription::LaRouteEstRouverte,
            2 => Inscription::SaiEstFondee,
            3 => Inscription::LaFlotteEstPartie,
            4 => Inscription::MegiddoEstTenue,
            5 => Inscription::MalkataSeDresse,
            6 => Inscription::AkhetatonSort,
            7 => Inscription::ElephantineCompte,
            8 => Inscription::ShedetRespire,
            9 => Inscription::LeOuadiRend,
            10 => Inscription::LeSinaiRend,
            default => null,
        };
    }

    /**
     * Les inscriptions réservées à un fil rouge, toutes missions confondues.
     * Elles ne se proposent jamais hors de leur acte, ni hors de leur mission.
     *
     * @return list<Inscription>
     */
    public static function inscriptionsReservees(): array
    {
        $reservees = [];

        for ($mission = 1; $mission <= 10; ++$mission) {
            $ouverture = self::ouverture($mission);
            $stele = self::stele($mission);

            if (null !== $ouverture) {
                $reservees[] = $ouverture;
            }

            if (null !== $stele) {
                $reservees[] = $stele;
            }
        }

        return $reservees;
    }

    public static function acte(GameSave $partie): ActeDuFilRouge
    {
        $ville = $partie->getVille();
        $mission = $partie->getMission() ?? 0;
        $enquete = self::enquete($partie);

        if (!\in_array(self::ouverture($mission), $ville->inscriptionsDechiffrees(), true)) {
            return ActeDuFilRouge::Commande;
        }

        if (null === $enquete || StatutDEnquete::Resolue !== $ville->dossierDe($enquete)?->getStatut()) {
            return ActeDuFilRouge::Obstacle;
        }

        if (!\in_array(self::stele($mission), $ville->inscriptionsDechiffrees(), true)) {
            return ActeDuFilRouge::Accomplissement;
        }

        return ActeDuFilRouge::Accompli;
    }

    /**
     * L'enquête qui porte l'acte II de cette mission. C'est elle qui se rejoue
     * jusqu'à être résolue : son échec définitif bloquerait la campagne.
     */
    public static function enquete(GameSave $partie): ?Enquete
    {
        return Enquete::duFilRouge($partie->getMission() ?? 0);
    }

    /**
     * Une inscription du fil rouge ne se propose qu'à son acte : la tablette
     * d'Ahmôsis d'abord, la stèle finale seulement une fois l'affaire close.
     * Sans cette réserve, on lirait la conclusion avant l'obstacle.
     */
    public static function inscriptionOuverte(GameSave $partie, Inscription $inscription): bool
    {
        if (!self::court($partie)) {
            return true;
        }

        $mission = $partie->getMission() ?? 0;

        return match (true) {
            $inscription === self::ouverture($mission) => ActeDuFilRouge::Commande === self::acte($partie),
            $inscription === self::stele($mission) => ActeDuFilRouge::Accomplissement === self::acte($partie),
            // Les tablettes des autres missions ne se lisent pas ici : elles
            // ne racontent pas cette histoire-là.
            \in_array($inscription, self::inscriptionsReservees(), true) => false,
            default => true,
        };
    }

    /**
     * L'inscription du fil rouge à lire maintenant, s'il y en a une. Elle
     * passe **avant** les autres : c'est ce que le roi attend.
     */
    public static function inscriptionDeLActe(GameSave $partie): ?Inscription
    {
        if (!self::court($partie)) {
            return null;
        }

        $mission = $partie->getMission() ?? 0;

        return match (self::acte($partie)) {
            ActeDuFilRouge::Commande => self::ouverture($mission),
            ActeDuFilRouge::Accomplissement => self::stele($mission),
            default => null,
        };
    }
}
