<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que l'Atelier sait faire, et ce qu'il y faut (doc 08, révisé).
 *
 * **Les recettes du doc 08 ne portaient qu'un ingrédient** — poterie = argile,
 * pain = blé. La joueuse en a voulu de vraies : une poterie ne se fait pas sans
 * feu, un pain ne cuit pas sans four. Elles sont donc réécrites, chacune sur ce
 * que l'artisanat égyptien réclamait réellement, et le document est à mettre à
 * jour en conséquence.
 *
 * Le **bois local** revient dans la plupart : c'est le combustible et l'outil de
 * l'atelier égyptien — le four du potier, celui du boulanger, le métier du
 * tisserand. Il n'était pas assez bon pour les charpentes de prestige, il l'était
 * pour brûler et pour tourner.
 *
 * **La bière se fait avec du pain** (Nakhtmin et les modèles funéraires de
 * l'Ancien Empire le montrent, et les analyses de résidus le confirment) : des
 * pains d'orge à peine cuits, émiettés et mis à fermenter. C'est la seule
 * recette qui en consomme une autre, et c'est ce que l'histoire dit.
 *
 * **Un ordre porte des lots, pas des pièces.** Un lot consomme ce qui suit et
 * rend plusieurs objets : on n'allume pas un four pour une seule jarre.
 */
enum Recette: string
{
    case Poterie = 'poterie';
    case Pain = 'pain';
    case Biere = 'biere';
    case Vannerie = 'vannerie';
    case Papyrus = 'papyrus';
    case Sandales = 'sandales';
    case Tissus = 'tissus';

    // La Forge, sur une matière que le Delta ne porte pas : le cuivre.
    case Outils = 'outils';
    case Armes = 'armes';

    // Craft de luxe : même Atelier, mais des matières qu'aucune région ne
    // porte toutes. Il ne s'ouvre qu'à un Entrepôt de haut niveau — on ne fait
    // pas d'orfèvrerie sans un commerce qui apporte l'or et la turquoise.
    case Bijoux = 'bijoux';
    case Statuettes = 'statuettes';
    case Vases = 'vases';

    public function libelle(): string
    {
        return match ($this) {
            self::Poterie => 'Poterie',
            self::Pain => 'Pain',
            self::Biere => 'Bière',
            self::Vannerie => 'Vannerie et cordages',
            self::Papyrus => 'Papyrus',
            self::Sandales => 'Sandales',
            self::Tissus => 'Tissus',
            self::Outils => 'Outils de cuivre',
            self::Armes => 'Armes de cuivre',
            self::Bijoux => 'Bijoux et amulettes',
            self::Statuettes => 'Statuettes et mobilier',
            self::Vases => 'Vases et statues',
        };
    }

    /**
     * Où la recette se travaille. L'Atelier et la Forge partagent la même
     * mécanique — ordres, lots, déblocage par niveau — et ne diffèrent que par
     * ce qu'ils savent faire.
     */
    public function batiment(): TypeDeBatiment
    {
        return match ($this) {
            self::Outils, self::Armes => TypeDeBatiment::Forge,
            default => TypeDeBatiment::Atelier,
        };
    }

    /**
     * Vrai quand l'objet produit n'a **pas encore d'usage propre** : il se
     * vend, et c'est tout.
     *
     * **Les armes n'en sont plus** (lot 10.3) : elles équipent les Medjaÿ, et
     * la qualité de la Forge décide de ce qu'elles valent au combat. Restent
     * les outils, en attente d'un système qui les consomme.
     *
     * L'interface doit le dire, comme elle le fait déjà des traits et des
     * spécialités endormis : promettre un usage qui n'existe nulle part
     * tromperait le joueur au moment où il engage ses matières.
     */
    public function produitDortEnAttendantSonUsage(): bool
    {
        return self::Outils === $this;
    }

    /**
     * Ce que la recette rend — la ressource fabriquée elle-même.
     */
    public function produit(): Ressource
    {
        return match ($this) {
            self::Outils => Ressource::Outils,
            self::Armes => Ressource::Armes,
            self::Bijoux => Ressource::Bijoux,
            self::Statuettes => Ressource::Statuettes,
            self::Vases => Ressource::Vases,
            self::Poterie => Ressource::Poterie,
            self::Pain => Ressource::Pain,
            self::Biere => Ressource::Biere,
            self::Vannerie => Ressource::Vannerie,
            self::Papyrus => Ressource::Papyrus,
            self::Sandales => Ressource::Sandales,
            self::Tissus => Ressource::Tissus,
        };
    }

    /**
     * Ce qu'un lot consomme, la ressource nommée et rien d'autre.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function ingredientsDunLot(): array
    {
        return match ($this) {
            // Argile façonnée, et du bois pour cuire : sans feu, pas de jarre.
            self::Poterie => [Ressource::Argile->value => 8, Ressource::BoisLocal->value => 3],
            // Le grain et le four.
            self::Pain => [Ressource::Ble->value => 10, Ressource::BoisLocal->value => 2],
            // Des pains d'orge à peine cuits, émiettés et mis à fermenter.
            self::Biere => [Ressource::Orge->value => 8, Ressource::Pain->value => 3],
            // Les roseaux tressés, liés de fil de lin.
            self::Vannerie => [Ressource::Roseaux->value => 8, Ressource::Lin->value => 2],
            // Les feuilles se collent l'une à l'autre à la colle de farine.
            self::Papyrus => [Ressource::Roseaux->value => 12, Ressource::Ble->value => 3],
            // Semelle de jonc, lanières de lin.
            self::Sandales => [Ressource::Roseaux->value => 6, Ressource::Lin->value => 3],
            // Le lin filé, et le métier qui s'use à le tisser.
            self::Tissus => [Ressource::Lin->value => 10, Ressource::BoisLocal->value => 2],
            // La lame de cuivre et son manche : une herminette, une houe, un
            // ciseau de tailleur de pierre.
            self::Outils => [Ressource::Cuivre->value => 6, Ressource::BoisLocal->value => 3],
            // La hache égyptienne : une lame de cuivre, une hampe de bois, et
            // des liens de lin pour tenir l'une à l'autre.
            self::Armes => [Ressource::Cuivre->value => 8, Ressource::BoisLocal->value => 3, Ressource::Lin->value => 2],
            // L'orfèvrerie funéraire : l'or du désert oriental, la turquoise
            // du Sinaï. Deux régions, donc deux routes.
            self::Bijoux => [Ressource::Or->value => 3, Ressource::Turquoise->value => 2],
            // Le mobilier de prestige : cèdre du Levant, ivoire de Nubie.
            self::Statuettes => [Ressource::BoisDeCedre->value => 4, Ressource::Ivoire->value => 2],
            // Les vases canopes : l'albâtre d'Hatnub, creusé au foret de
            // cuivre et au sable abrasif.
            self::Vases => [Ressource::Albatre->value => 6, Ressource::Cuivre->value => 2],
        };
    }

    /**
     * Ce qu'un lot coûte en deben, par-dessus les matières : l'outil qu'on
     * remplace, le tour qu'on répare, la main qu'on paie au façon.
     */
    public function debenDunLot(): int
    {
        return match ($this) {
            self::Pain, self::Vannerie, self::Sandales => 2,
            self::Poterie, self::Biere => 3,
            self::Papyrus => 4,
            self::Tissus, self::Outils => 5,
            self::Armes, self::Vases => 8,
            self::Statuettes => 15,
            self::Bijoux => 10,
        };
    }

    /**
     * Combien d'objets un lot rend. On n'allume pas un four pour une jarre.
     */
    public function piecesDunLot(): int
    {
        return match ($this) {
            self::Pain => 5,
            self::Poterie, self::Biere, self::Vannerie, self::Sandales => 4,
            self::Papyrus, self::Tissus, self::Outils, self::Armes => 3,
            // Une pièce de prestige se fait à l'unité : on ne fond pas des
            // bijoux par fournées.
            self::Bijoux, self::Statuettes, self::Vases => 1,
        };
    }

    /**
     * Niveau d'Atelier qui débloque la recette (doc 01) : la complexité de
     * l'artisanat croît avec le bâtiment, et les sept tiennent en quatre
     * niveaux pour rester toutes atteignables au Delta, dont le plafond
     * régional est de cinq.
     */
    public function niveauRequis(): int
    {
        return match ($this) {
            // Atelier (doc 01) : la complexité de l'artisanat croît avec le
            // bâtiment, et les sept tiennent en quatre niveaux.
            self::Poterie, self::Pain => 1,
            self::Biere, self::Vannerie => 2,
            self::Papyrus, self::Sandales => 3,
            self::Tissus => 4,
            // Forge (doc 01) : outils au premier niveau, armes au second.
            self::Outils => 1,
            self::Armes => 2,
            // Craft de luxe : le doc 01 veut un Atelier de niveau 8, en plus
            // de l'Entrepôt — voir `deblocageSupplementaire()`.
            self::Bijoux, self::Statuettes, self::Vases => 8,
        };
    }

    /**
     * Quinzaines qu'un lot demande à un atelier bien tenu. Les procédés longs
     * — bandes pressées du papyrus, filage et tissage du lin — en demandent
     * deux (doc 01, « logique de complexité »).
     */
    public function quinzainesDunLot(): int
    {
        return match ($this) {
            self::Papyrus, self::Tissus, self::Armes => 2,
            // Le travail d'orfèvre ne se presse pas.
            self::Bijoux, self::Statuettes, self::Vases => 3,
            default => 1,
        };
    }

    /**
     * Une seconde condition, portée par un **autre bâtiment** que celui où la
     * recette se travaille (doc 01, doc 08).
     *
     * Le craft de luxe se fait à l'Atelier mais s'ouvre à l'**Entrepôt de
     * niveau 8** : un commerce longue distance conséquent — l'or, la
     * turquoise, l'ivoire — suppose une logistique développée, pas un simple
     * entrepôt de départ.
     *
     * **Conséquence voulue** : au Delta, dont le plafond régional est de cinq,
     * le luxe reste hors d'atteinte. C'est ce que le doc 01 dit expressément
     * de la région d'apprentissage.
     *
     * @return array{TypeDeBatiment, int}|null
     */
    public function deblocageSupplementaire(): ?array
    {
        return match ($this) {
            self::Bijoux, self::Statuettes, self::Vases => [TypeDeBatiment::Entrepot, 8],
            default => null,
        };
    }

    public function estDeLuxe(): bool
    {
        return null !== $this->deblocageSupplementaire();
    }

    /**
     * Les recettes que ce bâtiment sait faire à ce niveau.
     *
     * @return list<self>
     */
    public static function pour(TypeDeBatiment $batiment, int $niveau): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $recette): bool => $recette->batiment() === $batiment
                && $recette->niveauRequis() <= $niveau,
        ));
    }

    /**
     * Les bâtiments qui fabriquent quelque chose.
     *
     * @return list<TypeDeBatiment>
     */
    public static function batimentsQuiFabriquent(): array
    {
        return [TypeDeBatiment::Atelier, TypeDeBatiment::Forge];
    }

    /**
     * Ce qu'un lot coûte en tout, deben compris — ce à quoi la marge de
     * transformation se rapporte (`PrixDuMarche::MARGE_DE_TRANSFORMATION`).
     */
    public function coutDunLot(): int
    {
        $total = $this->debenDunLot();

        foreach ($this->ingredientsDunLot() as $valeur => $quantite) {
            $total += (PrixDuMarche::pour(Ressource::from($valeur)) ?? 0) * $quantite;
        }

        return $total;
    }
}
