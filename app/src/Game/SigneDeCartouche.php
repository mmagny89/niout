<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les signes qu'on lit dans un cartouche royal, un par un (doc 10).
 *
 * `CartoucheRoyal` donne le nom entier, ses codes de Gardiner et sa lecture ;
 * il ne dit pas **ce que chaque signe y fait**. C'est pourtant la leçon : le
 * cartouche mêle des sons — le filet d'eau note *n* — à des mots entiers — le
 * disque solaire dit « Rê » à lui seul —, et c'est cette alternance qui fait
 * l'écriture égyptienne.
 *
 * **Seuls figurent ici les signes dont la lecture est établie** et qu'on peut
 * vérifier dans n'importe quelle grammaire. Un cartouche qui porterait un
 * signe absent de cette liste n'est pas proposé à la lecture
 * (`LectureDeCartouche::eligibles()`) : mieux vaut en offrir moins que d'en
 * approximer un, la règle du projet étant qu'on n'affiche rien plutôt qu'une
 * approximation.
 *
 * **La valeur de l'énumération est le code de Gardiner**, et le dessin vient du
 * cartouche lui-même : jamais un glyphe recopié ici, que rien ne confronterait
 * à son code. `CodesDeGardinerTest` vérifie déjà celui des cartouches.
 */
enum SigneDeCartouche: string
{
    case DisqueSolaire = 'N5';
    case Panier = 'V30';
    case Maat = 'C10';
    case MaatVariante = 'C10A';
    case Damier = 'Y5';
    case Scarabee = 'L1';
    case Colonne = 'O29';
    case BrasLeves = 'D28';
    case BrasAuBaton = 'D45';
    case TroisTraits = 'Z2A';
    case FiletDEau = 'N35';

    /**
     * Ce que le signe y fait : noter un son, dire un mot entier, ou marquer le
     * pluriel.
     */
    public function nature(): string
    {
        return match ($this) {
            self::FiletDEau, self::Damier => 'son',
            self::TroisTraits => 'marque',
            default => 'mot',
        };
    }

    public function libelleDeNature(): string
    {
        return match ($this->nature()) {
            'son' => 'note un son',
            'marque' => 'marque le pluriel',
            default => 'dit un mot entier',
        };
    }

    /**
     * La translittération, telle que les grammaires l'écrivent.
     */
    public function translitteration(): string
    {
        return match ($this) {
            self::DisqueSolaire => 'rꜥ',
            self::Panier => 'nb',
            self::Maat, self::MaatVariante => 'mꜣꜥt',
            self::Damier => 'mn',
            self::Scarabee => 'ḫpr',
            self::Colonne => 'ꜥꜣ',
            self::BrasLeves => 'kꜣ',
            self::BrasAuBaton => 'ḏsr',
            self::TroisTraits => 'w',
            self::FiletDEau => 'n',
        };
    }

    /**
     * Ce que le signe veut dire dans un nom de trône.
     */
    public function sens(): string
    {
        return match ($this) {
            self::DisqueSolaire => 'Rê, le dieu soleil',
            self::Panier => 'maître, seigneur',
            self::Maat, self::MaatVariante => 'Maât, l\'ordre juste',
            self::Damier => 'demeurer, être stable',
            self::Scarabee => 'devenir, se manifester',
            self::Colonne => 'grand',
            self::BrasLeves => 'le ka, la force vitale',
            self::BrasAuBaton => 'sacré',
            self::TroisTraits => 'plusieurs, au pluriel',
            self::FiletDEau => 'le son n',
        };
    }

    /**
     * Le libellé complet d'un choix : ce que le signe se lit, et ce qu'il dit.
     */
    public function libelle(): string
    {
        return \sprintf('%s — %s', $this->translitteration(), $this->sens());
    }

    /**
     * Deux variantes d'un même signe se lisent pareil : on les confond à la
     * correction, plutôt que de refuser une réponse juste.
     */
    public function seLitCommeLUnAutre(self $autre): bool
    {
        return $this->translitteration() === $autre->translitteration();
    }
}
