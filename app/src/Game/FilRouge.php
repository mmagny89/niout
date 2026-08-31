<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Le fil rouge d'une mission, en trois actes (doc 09, doc 10).
 *
 * **Écrit pour la mission 1 seulement**, et c'est délibéré : le doc 10 pose
 * une structure répétable, mais écrire les dix fils rouges avant d'avoir joué
 * le premier reviendrait à écrire dix fois la même erreur. La Phase 8
 * généralisera.
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
    public const int MISSION = 1;

    /**
     * Le fil rouge ne court que sur la mission qu'il raconte. Ailleurs — les
     * autres missions, le mode Aventure —, il n'y en a pas encore, et les
     * inscriptions qu'il réserve redeviennent des inscriptions ordinaires.
     */
    public static function court(GameSave $partie): bool
    {
        return self::MISSION === $partie->getMission();
    }

    public static function acte(GameSave $partie): ActeDuFilRouge
    {
        $ville = $partie->getVille();

        if (!\in_array(Inscription::CommandeDAhmosis, $ville->inscriptionsDechiffrees(), true)) {
            return ActeDuFilRouge::Commande;
        }

        if (StatutDEnquete::Resolue !== $ville->dossierDe(self::enquete())?->getStatut()) {
            return ActeDuFilRouge::Obstacle;
        }

        if (!\in_array(Inscription::LaRouteEstRouverte, $ville->inscriptionsDechiffrees(), true)) {
            return ActeDuFilRouge::Accomplissement;
        }

        return ActeDuFilRouge::Accompli;
    }

    /**
     * L'enquête qui porte l'acte II. C'est elle qui se rejoue jusqu'à être
     * résolue : son échec définitif bloquerait la mission.
     */
    public static function enquete(): Enquete
    {
        return Enquete::PassageCoupe;
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

        return match ($inscription) {
            Inscription::CommandeDAhmosis => ActeDuFilRouge::Commande === self::acte($partie),
            Inscription::LaRouteEstRouverte => ActeDuFilRouge::Accomplissement === self::acte($partie),
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

        return match (self::acte($partie)) {
            ActeDuFilRouge::Commande => Inscription::CommandeDAhmosis,
            ActeDuFilRouge::Accomplissement => Inscription::LaRouteEstRouverte,
            default => null,
        };
    }
}
