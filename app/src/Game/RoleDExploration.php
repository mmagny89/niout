<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les rôles envoyés sur le terrain (doc 04).
 *
 * L'exploration se fait en deux temps : l'éclaireur reconnaît toute case
 * inconnue, puis une action complémentaire s'impose — ou non — selon ce qu'il a
 * trouvé. Le joueur ne décide jamais à l'avance d'envoyer une grosse équipe.
 *
 * Seul l'éclaireur est jouable pour l'instant : l'émissaire suppose des PNJ, le
 * chef d'expédition des zones lourdes, et l'escorte des Medjaÿ. La première
 * mission se joue en difficulté 0, sans aucune zone à bandits — l'éclaireur y
 * suffit.
 */
enum RoleDExploration: string
{
    case Eclaireur = 'eclaireur';
    case Emissaire = 'emissaire';
    case ChefDExpedition = 'chef_expedition';

    public function libelle(): string
    {
        return match ($this) {
            self::Eclaireur => 'Éclaireur',
            self::Emissaire => 'Émissaire',
            self::ChefDExpedition => 'Chef d\'expédition',
        };
    }

    public function mission(): string
    {
        return match ($this) {
            self::Eclaireur => 'Reconnaît la case et en révèle le contenu. Rapide, peu coûteux, sans combat.',
            self::Emissaire => 'Noue le contact avec une population locale : commerce, quêtes, relations.',
            self::ChefDExpedition => 'Encadre une expédition lourde vers une mine ou une carrière éloignée.',
        };
    }

    /**
     * Solde du rôle, en or (doc 04).
     */
    public function cout(): int
    {
        return match ($this) {
            self::Eclaireur => 10,
            self::Emissaire => 30,
            self::ChefDExpedition => 50,
        };
    }

    /**
     * Solde réellement dû pour une case à cette distance de la ville.
     *
     * **Les cases à moins de trois cases de la ville se reconnaissent
     * gratuitement**, en orthogonal comme en diagonale : assez proche pour
     * qu'un éclaireur y aille sans qu'on lui compte sa peine. Faire payer le
     * premier pas d'une partie neuve reviendrait à taxer le joueur pour
     * découvrir où il vient d'être envoyé. Les vivres, eux, restent dus —
     * l'éclaireur mange, même à une heure de marche.
     */
    public function coutPourUneDistance(int $distance): int
    {
        return $distance < 3 ? 0 : $this->cout();
    }

    /**
     * Vivres emportés pour la route (doc 04). On ne part pas explorer le désert
     * les mains vides ; c'est ce qui donne à la nourriture un usage avant même
     * qu'une population soit à nourrir.
     *
     * N'importe quelle nourriture fait l'affaire — blé, orge, dattes, poisson.
     */
    public function provisions(): int
    {
        return match ($this) {
            self::Eclaireur => 5,
            self::Emissaire => 10,
            self::ChefDExpedition => 20,
        };
    }

    /**
     * Rôles réellement jouables à ce stade du développement.
     *
     * @return list<self>
     */
    public static function disponibles(): array
    {
        return [self::Eclaireur];
    }
}
