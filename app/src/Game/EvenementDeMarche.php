<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un fait daté qui infléchit le marché d'une cité pour quelques quinzaines
 * (`docs/plan-pnj-palier-a.md` § 4.2).
 *
 * **Tout est inventé**, même quand le thème est attesté : une disette au Levant, une foire,
 * un chantier royal qui appelle des outils. Le jeu ne prétend pas dater un événement réel.
 *
 * Chaque événement dit **combien** il pèse sur une marchandise, signe compris : positif,
 * il avantage la famille (la cité manque de ce qu'on lui vend, ou regorge de ce qu'on lui
 * achète), négatif il la désavantage. Le **pourquoi** se déduit du même endroit — un texte
 * qui divergerait du chiffre qu'il raconte est exactement ce que le projet s'interdit.
 *
 * Restent à écrire — voir le plan, lot suivant — ceux qui touchent autre chose qu'un prix :
 * le deuil de cour (route ralentie), les troubles (pillage) et la guerre (fermeture
 * durable, et son cadre strict).
 */
enum EvenementDeMarche: string
{
    case Disette = 'disette';
    case BonneRecolte = 'bonne-recolte';
    case Foire = 'foire';
    case BesoinDUnOuvrage = 'besoin-d-un-ouvrage';

    /** Ce que pèse un événement de fond, en points de pourcentage. */
    public const int POIDS = 12;

    /** Ce que pèse une foire : un marché animé, pas une aubaine. */
    public const int POIDS_DE_LA_FOIRE = 4;

    /** Ce que la cité appelle quand elle prépare un ouvrage. */
    private const array OUVRAGES = [
        Ressource::Outils,
        Ressource::Armes,
        Ressource::Calcaire,
        Ressource::Gres,
    ];

    public function libelle(): string
    {
        return match ($this) {
            self::Disette => 'Disette',
            self::BonneRecolte => 'Bonne récolte',
            self::Foire => 'Foire',
            self::BesoinDUnOuvrage => 'Besoin d\'un ouvrage',
        };
    }

    /**
     * Combien l'événement pèse sur cette marchandise chez ce partenaire, pour la famille.
     */
    public function pointsSur(PartenaireCommercial $partenaire, Ressource $ressource): int
    {
        $achete = $partenaire->acheteDe($ressource);
        $vend = $partenaire->vendDe($ressource);

        if (!$achete && !$vend) {
            return 0;
        }

        $vivres = FamilleDeRessource::Vivres === $ressource->famille();

        return match ($this) {
            // Il manque de vivres : ce qu'on lui vend se paie mieux, ce qu'il vend se garde.
            self::Disette => $vivres ? ($achete ? self::POIDS : -self::POIDS) : 0,
            self::BonneRecolte => $vivres ? ($achete ? -self::POIDS : self::POIDS) : 0,
            self::BesoinDUnOuvrage => $achete && \in_array($ressource, self::OUVRAGES, true) ? self::POIDS : 0,
            self::Foire => self::POIDS_DE_LA_FOIRE,
        };
    }

    /**
     * La cause, à dire au joueur : « Disette à Byblos ».
     */
    public function cause(PartenaireCommercial $partenaire): string
    {
        return \sprintf('%s — %s', $this->libelle(), $partenaire->nom);
    }
}
