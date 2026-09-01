<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Un objectif chiffré d'une mission (doc 09).
 *
 * **Un objectif atteint le reste** : c'est le principe qui commande tout le
 * reste. Une trésorerie qu'on dépense, une population qui fond, une ressource
 * qu'on vend — rien de tout cela ne doit reprendre ce qui a été obtenu, sans
 * quoi le joueur serait puni d'avoir joué. Les mesures cumulatives du lot 8.1
 * existent pour ça.
 */
final readonly class ObjectifDeMission
{
    public function __construct(
        public TypeDObjectif $type,
        public int $seuil,
        public ?Ressource $ressource = null,
        public ?TypeDeBatiment $batiment = null,
    ) {
    }

    /**
     * Ce que le pharaon demande, dit dans ses termes.
     */
    public function libelle(): string
    {
        return match ($this->type) {
            TypeDObjectif::Richesse => \sprintf('Réunir %d deben en caisse', $this->seuil),
            TypeDObjectif::Population => \sprintf('Porter la ville à %d habitants', $this->seuil),
            TypeDObjectif::Commerce => \sprintf('Échanger pour %d deben de marchandises', $this->seuil),
            TypeDObjectif::Infrastructure => \sprintf(
                'Monter %s au niveau %d',
                $this->batiment?->libelle() ?? 'un bâtiment',
                $this->seuil,
            ),
            TypeDObjectif::Renommee => \sprintf('Atteindre le rang « %s »', $this->palierVise()->libelle()),
            TypeDObjectif::Ressource => \sprintf(
                'Rapporter %d unités de %s',
                $this->seuil,
                $this->ressource?->libelle() ?? 'ressource',
            ),
        };
    }

    /**
     * Où en est le joueur.
     *
     * **Les six mesures existent, et chacune bouge** — c'est vérifié une par
     * une, pas déclaré. Un objectif indexé sur une valeur que rien ne fait
     * changer est le piège d'`ajusterRenommee()` ; un drapeau « pas encore
     * mesuré » ne l'aurait pas évité, un test qui fait bouger chaque mesure
     * si.
     */
    public function avancement(GameSave $partie): int
    {
        $ville = $partie->getVille();

        return match ($this->type) {
            TypeDObjectif::Richesse => $ville->getDeben(),
            TypeDObjectif::Population => $ville->population(),
            TypeDObjectif::Renommee => $partie->getFamille()->getRenommee(),
            TypeDObjectif::Infrastructure => null !== $this->batiment
                ? ($ville->batimentDeType($this->batiment)?->getNiveau() ?? 0)
                : 0,
            TypeDObjectif::Commerce => $ville->getValeurEchangee(),
            TypeDObjectif::Ressource => null !== $this->ressource
                ? $ville->ressourceRapportee($this->ressource)
                : 0,
        };
    }

    /**
     * Le seuil réellement visé, dans l'unité de la mesure. La renommée se
     * demande en palier et se compte en points : c'est le point d'entrée du
     * palier qu'il faut atteindre.
     */
    public function seuilMesure(): int
    {
        return TypeDObjectif::Renommee === $this->type
            ? $this->pointsDuPalier()
            : $this->seuil;
    }

    public function estAtteint(GameSave $partie): bool
    {
        return $this->avancement($partie) >= $this->seuilMesure();
    }

    /**
     * Le palier de renommée visé, du rang 1 (Inconnue) au rang 5 (Illustre).
     */
    public function palierVise(): PalierDeRenommee
    {
        return PalierDeRenommee::cases()[min(4, max(0, $this->seuil - 1))];
    }

    private function pointsDuPalier(): int
    {
        // Les paliers du doc 13 s'ouvrent tous les vingt points : Modeste à 20,
        // Reconnue à 40, Respectée à 60, Illustre à 80.
        return max(0, ($this->seuil - 1) * 20);
    }
}
