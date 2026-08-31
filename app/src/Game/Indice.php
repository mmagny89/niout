<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les indices d'une enquête (doc 10).
 *
 * Trois natures, et il faut les distinguer sous peine de faire une liste de
 * courses :
 *
 * - **Concordant** : il concourt à la conclusion. Il en faut un certain nombre.
 * - **Optionnel** : il éclaire sans être nécessaire — la récompense d'un
 *   joueur qui fouille plus que le strict minimum.
 * - **Trompeur** : une fausse piste. Il n'empêche rien, mais il oriente mal
 *   celui qui compte au lieu de lire.
 *
 * Chaque indice a une **source** : le terrain, qu'un éclaireur rapporte, ou un
 * témoignage, qu'un émissaire recueille (doc 04, doc 10). Le lot 7.5 branchera
 * les seconds ; les premiers se trouvent déjà, sur les cases où quelque chose
 * se trame.
 */
enum Indice: string
{
    // Le passage coupé — l'enquête principale.
    case BorneRenversee = 'borne_renversee';
    case TracesDeCampement = 'traces_de_campement';
    case DigueRompue = 'digue_rompue';
    case OstraconDeGarnison = 'ostracon_de_garnison';
    case VieuxPuitsATarir = 'vieux_puits_a_tarir';

    // Les carrières abandonnées.
    case OutilsRouilles = 'outils_rouilles';
    case FilonEpuise = 'filon_epuise';
    case MarqueDeCrue = 'marque_de_crue';

    // La rumeur de la caravane.
    case RecitDuPremierCaravanier = 'recit_du_premier_caravanier';
    case RecitDuSecond = 'recit_du_second';
    case RegistreDuPeage = 'registre_du_peage';

    public function enquete(): Enquete
    {
        return match ($this) {
            self::BorneRenversee, self::TracesDeCampement, self::DigueRompue,
            self::OstraconDeGarnison, self::VieuxPuitsATarir => Enquete::PassageCoupe,
            self::OutilsRouilles, self::FilonEpuise, self::MarqueDeCrue => Enquete::CarrieresAbandonnees,
            self::RecitDuPremierCaravanier, self::RecitDuSecond,
            self::RegistreDuPeage => Enquete::RumeurDeLaCaravane,
        };
    }

    public function texte(): string
    {
        return match ($this) {
            self::BorneRenversee => 'Une borne de chemin renversée, et non tombée : on l\'a poussée.',
            self::TracesDeCampement => 'Un foyer éteint, des tessons, des empreintes de sandales trop nombreuses pour une famille.',
            self::DigueRompue => 'La digue du canal est ouverte sur trois coudées. La brèche est nette.',
            self::OstraconDeGarnison => 'Un ostracon : « à la garnison, rien à signaler depuis deux saisons ». Le scribe n\'a rien vu — ou n\'a rien voulu voir.',
            self::VieuxPuitsATarir => 'Un puits presque à sec, à une demi-journée de là. On y venait, autrefois.',
            self::OutilsRouilles => 'Des ciseaux de cuivre laissés en place, encore fichés dans la pierre.',
            self::FilonEpuise => 'Le front de taille bute sur du calcaire stérile : il n\'y avait plus rien à prendre.',
            self::MarqueDeCrue => 'Une marque de crue haute sur la paroi, bien au-dessus des cabanes.',
            self::RecitDuPremierCaravanier => 'Le premier jure que la piste est sûre, et qu\'il l\'a prise le mois dernier.',
            self::RecitDuSecond => 'Le second jure le contraire, et décrit un ouadi que le premier n\'a pas nommé.',
            self::RegistreDuPeage => 'Le registre du péage ne porte aucun passage depuis deux quinzaines.',
        };
    }

    public function nature(): NatureDIndice
    {
        return match ($this) {
            self::BorneRenversee, self::TracesDeCampement, self::DigueRompue,
            self::OutilsRouilles, self::FilonEpuise,
            self::RecitDuSecond, self::RegistreDuPeage => NatureDIndice::Concordant,
            self::VieuxPuitsATarir, self::MarqueDeCrue => NatureDIndice::Optionnel,
            self::OstraconDeGarnison, self::RecitDuPremierCaravanier => NatureDIndice::Trompeur,
        };
    }

    /**
     * D'où il vient. Le terrain se fouille, le témoignage se recueille.
     */
    public function source(): SourceDIndice
    {
        return match ($this) {
            self::OstraconDeGarnison, self::RecitDuPremierCaravanier,
            self::RecitDuSecond, self::RegistreDuPeage => SourceDIndice::Temoignage,
            default => SourceDIndice::Terrain,
        };
    }
}
