<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les huit divinités que la ville peut honorer (doc 07).
 *
 * **Aucun dieu patron imposé** : le joueur cultive plusieurs faveurs en
 * parallèle, chacune évoluant pour son compte. C'est ce qui fait de la
 * répartition des offrandes une stratégie — porter Ptah pour bâtir vite, ou
 * Sekhmet avant que la fièvre ne passe — plutôt qu'un choix unique fait une
 * fois pour toutes au début de la partie.
 *
 * Le panthéon est du **contenu**, jamais de l'état : seule la clé d'une
 * divinité est persistée (`FaveurDivine`), avec la valeur de sa faveur. Le
 * nom, le domaine et l'effet vivent ici, comme les partenaires commerciaux
 * vivent dans leur catalogue.
 *
 * Les huit sont toutes attestées au Nouvel Empire, et le doc 07 le vérifie
 * divinité par divinité : Amon-Rê est le dieu national de la période, culte le
 * plus puissant d'Égypte ; Sobek y a prévalu sous sa forme Sobek-Horus.
 */
enum Divinite: string
{
    case AmonRe = 'amon_re';
    case Hapi = 'hapi';
    case Osiris = 'osiris';
    case Ptah = 'ptah';
    case Sobek = 'sobek';
    case Sekhmet = 'sekhmet';
    case Isis = 'isis';
    case Thot = 'thot';

    /**
     * La faveur d'un dieu qu'on n'a jamais honoré.
     *
     * **Quarante, et non cinquante.** Le doc 07 annonce « valeur de départ
     * neutre à 50 » tout en plaçant le palier Favorable à partir de 50 : suivi
     * à la lettre, il offrirait au joueur huit bonus actifs sans qu'il ait
     * jamais mis les pieds au Temple. On démarre donc **dans** la bande
     * Neutre, sans effet ni bonus ni malus — la contradiction du document est
     * tranchée du côté de ses paliers, qui sont sa partie chiffrée.
     */
    public const int FAVEUR_DE_DEPART = 40;

    public const int FAVEUR_MINIMALE = 0;
    public const int FAVEUR_MAXIMALE = 100;

    public function libelle(): string
    {
        return match ($this) {
            self::AmonRe => 'Amon-Rê',
            self::Hapi => 'Hâpi',
            self::Osiris => 'Osiris',
            self::Ptah => 'Ptah',
            self::Sobek => 'Sobek',
            self::Sekhmet => 'Sekhmet',
            self::Isis => 'Isis',
            self::Thot => 'Thot',
        };
    }

    /**
     * Le domaine du dieu, tel que le doc 07 le pose.
     */
    public function domaine(): string
    {
        return match ($this) {
            self::AmonRe => 'Autorité et renommée',
            self::Hapi => 'La crue et le Nil',
            self::Osiris => 'L\'agriculture et l\'au-delà',
            self::Ptah => 'L\'artisanat et la construction',
            self::Sobek => 'Le fleuve et ceux qui y naviguent',
            self::Sekhmet => 'La maladie collective, et sa guérison',
            self::Isis => 'La magie et la protection au combat',
            self::Thot => 'La sagesse et l\'écriture',
        };
    }

    /**
     * Ce que la faveur de ce dieu change dans la ville, dit au joueur avant
     * qu'il n'offre. C'est ce qui distingue une divinité d'une autre à ses
     * yeux : sans cette phrase, huit noms se valent.
     */
    public function effet(): string
    {
        return match ($this) {
            self::AmonRe => 'Fait parler de votre famille au loin, et rend la ville plus attirante.',
            self::Hapi => 'Incline la crue de l\'année vers la générosité.',
            self::Osiris => 'Fait rendre davantage aux champs, de Perèt à Chémou.',
            self::Ptah => 'Abrège les chantiers.',
            self::Sobek => 'Veille sur la pêche et sur ce qui voyage par l\'eau.',
            self::Sekhmet => 'Écarte la fièvre, et l\'abrège quand elle a pris.',
            self::Isis => 'Protège les combattants et referme leurs blessures.',
            self::Thot => 'Éclaire les écrits et ce qu\'ils dissimulent.',
        };
    }

    /**
     * Le doc 07 distingue soigneusement Isis de Sekhmet, et l'écran doit le
     * dire aussi : elles paraissent sinon faire la même chose. **Isis protège
     * l'individu** — un homme au combat, ses blessures. **Sekhmet gouverne le
     * collectif** — l'issue d'une bataille, et l'épidémie qui traverse une
     * ville.
     */
    public function precision(): ?string
    {
        return match ($this) {
            self::Isis => 'Isis protège l\'homme ; Sekhmet décide du sort de tous.',
            self::Sekhmet => 'Celle qui envoie la maladie est celle qui la guérit : ses prêtres étaient les médecins de l\'Égypte.',
            default => null,
        };
    }

    /**
     * Un dieu dont le domaine n'existe pas encore dans le jeu **le dit**.
     *
     * Isis attend le combat (Phase 10), Thot les énigmes (Phase 7). Ils sont
     * offrables — le panthéon serait faux sans eux —, mais promettre un effet
     * qui ne s'applique nulle part tromperait le joueur au moment même où il
     * choisit à qui donner. Même règle que `SpecialiteDeChef::agitDeja()`.
     */
    public function agitDeja(): bool
    {
        return match ($this) {
            self::Isis, self::Thot => false,
            default => true,
        };
    }

    /**
     * Ce qu'on répond au joueur qui veut honorer un dieu encore sans emploi.
     */
    public function attente(): ?string
    {
        return match ($this) {
            self::Isis => 'Aucune bataille ne se livre encore : sa protection attend les Medjaÿ.',
            self::Thot => 'Aucun texte ne résiste encore : sa sagesse attend la Maison des scribes.',
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function pantheon(): array
    {
        return self::cases();
    }
}
