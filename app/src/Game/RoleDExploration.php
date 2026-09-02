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
 * Trois rôles sont jouables : l'éclaireur, qui lève le brouillard ; le
 * prospecteur, qui sonde une case reconnue à la recherche d'un filon ; et le
 * chef d'expédition, qui mène les Medjaÿ déloger une bande (lot 10.5).
 * L'émissaire, lui, se déclenche depuis les enquêtes.
 */
enum RoleDExploration: string
{
    case Eclaireur = 'eclaireur';
    case Emissaire = 'emissaire';
    case ChefDExpedition = 'chef_expedition';
    case Prospecteur = 'prospecteur';

    public function libelle(): string
    {
        return match ($this) {
            self::Eclaireur => 'Éclaireur',
            self::Emissaire => 'Émissaire',
            self::ChefDExpedition => 'Chef d\'expédition',
            self::Prospecteur => 'Prospecteur',
        };
    }

    public function mission(): string
    {
        return match ($this) {
            self::Eclaireur => 'Reconnaît la case et en révèle le contenu. Rapide, peu coûteux, sans combat.',
            self::Emissaire => 'Va parler aux gens d\'une case déjà reconnue, et rapporte ce qui s\'y dit. Les témoignages nourrissent les enquêtes.',
            self::ChefDExpedition => 'Mène la troupe déloger une bande installée sur une case reconnue. Le combat se résout à l\'arrivée ; la case prise le reste.',
            self::Prospecteur => 'Sonde une case déjà reconnue : il rouvre un filon épuisé ou en met un nouveau au jour. Il peut rentrer bredouille.',
        };
    }

    /**
     * **Un éclaireur va vers l'inconnu, un émissaire va vers les gens**
     * (doc 04, doc 10). Le premier reconnaît une case qu'on n'a jamais vue ;
     * le second noue le contact avec une population locale, ce qui suppose
     * qu'il y ait quelqu'un — donc une case déjà reconnue.
     *
     * C'est ce qui donne enfin à l'Émissaire un emploi propre : jusqu'ici, il
     * faisait le travail de l'éclaireur en plus cher.
     */
    public function viseUneCaseInconnue(): bool
    {
        return match ($this) {
            self::Eclaireur => true,
            // Le prospecteur sonde un sol qu'on connaît déjà : on ne cherche
            // pas un filon là où l'on ignore encore ce qu'il y a. Le chef
            // d'expédition, lui, mène une troupe sur une bande **repérée** : on
            // ne part pas en armes vers une case dont on ignore ce qu'elle
            // porte.
            self::Emissaire, self::Prospecteur, self::ChefDExpedition => false,
        };
    }

    /**
     * Seul l'émissaire a besoin d'un endroit où consigner ce qu'on lui dit : un
     * témoignage sans dossier se perd. Le prospecteur, lui, rapporte un filon,
     * qui tient sur la carte et non dans un registre.
     */
    public function exigeLaMaisonDesScribes(): bool
    {
        return self::Emissaire === $this;
    }

    /**
     * **Le rayon gratuit vaut pour la reconnaissance, pas pour le travail.**
     * Ne pas faire payer le premier pas d'une partie neuve est une chose ;
     * offrir la prospection sous les murs de la ville en serait une autre — le
     * joueur rouvrirait ses filons épuisés sans jamais rien engager, et
     * l'épuisement cesserait de compter.
     */
    public function beneficieDuRayonGratuit(): bool
    {
        return self::Prospecteur !== $this;
    }

    /**
     * Solde du rôle, en deben (doc 04).
     */
    public function cout(): int
    {
        return match ($this) {
            self::Eclaireur => 10,
            self::Emissaire => 30,
            self::ChefDExpedition => 50,
            // Plus cher que l'éclaireur, moins que l'expédition lourde : une
            // équipe de sondeurs, pas une caravane.
            self::Prospecteur => 40,
        };
    }

    /**
     * Solde en deben réellement dû pour une case à cette distance de la ville.
     *
     * **Les cases à moins de trois cases de la ville se reconnaissent
     * entièrement gratuitement**, en orthogonal comme en diagonale : assez
     * proche pour qu'un éclaireur y aille sans qu'on lui compte sa peine, ni
     * en deben ni en vivres (décision de la joueuse). Faire payer le premier pas
     * d'une partie neuve reviendrait à taxer le joueur pour découvrir où il
     * vient d'être envoyé. Au-delà, l'expédition coûte les deux à la fois —
     * voir `provisionsPourUneDistance()`.
     */
    public function coutPourUneDistance(int $distance): int
    {
        return $distance < 3 && $this->beneficieDuRayonGratuit() ? 0 : $this->cout();
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
            self::Prospecteur => 15,
        };
    }

    /**
     * Vivres réellement dus pour une case à cette distance de la ville — nuls
     * dans le même rayon gratuit que `coutPourUneDistance()` : une case à
     * moins de trois cases de la ville ne coûte rien du tout, ni en deben ni en
     * vivres (décision de la joueuse).
     */
    public function provisionsPourUneDistance(int $distance): int
    {
        return $distance < 3 && $this->beneficieDuRayonGratuit() ? 0 : $this->provisions();
    }

    /**
     * **Le seul rôle qui parte en armes** (doc 03, doc 04, lot 10.5). Il
     * emmène les Medjaÿ disponibles, et c'est à son arrivée que le combat se
     * résout — un assaut se prépare, il ne se déclenche pas d'un bouton sur la
     * carte.
     *
     * C'est aussi la réponse à ce qu'il coûtait sans rien faire de plus qu'un
     * éclaireur : cinquante deben et vingt vivres achètent une troupe en
     * route, pas une reconnaissance de luxe.
     */
    public function meneLaTroupe(): bool
    {
        return self::ChefDExpedition === $this;
    }

    /**
     * Rôles réellement jouables à ce stade du développement.
     *
     * @return list<self>
     */
    public static function disponibles(): array
    {
        return [self::Eclaireur, self::Prospecteur, self::ChefDExpedition];
    }
}
