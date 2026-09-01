<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les chantiers que les pharaons ont **réellement** fait bâtir (doc 09).
 *
 * Chaque quête de chantier cite un monument attesté, avec deux ou trois
 * phrases sur ce qu'il fut. C'est la même exigence qu'aux lots 7.0 et 7.2, et
 * le même garde-fou : **ce qu'on apprend en jouant doit être vrai**. Un
 * monument inventé pour les besoins d'une quête trahirait le propos du projet.
 *
 * Un chantier par pharaon commanditaire de la campagne, et un seul — Ramsès IV
 * en commandite deux missions et n'a donc qu'un chantier, ce qui est cohérent :
 * c'est le même homme.
 */
enum ChantierRoyal: string
{
    case PyramideDAbydos = 'pyramide_abydos';
    case ObelisquesDeKarnak = 'obelisques_karnak';
    case DeirElBahari = 'deir_el_bahari';
    case AkhMenou = 'akh_menou';
    case TempleDeLouxor = 'temple_de_louxor';
    case GrandTempleDAton = 'grand_temple_aton';
    case TempleDAbydos = 'temple_abydos';
    case MedinetHabou = 'medinet_habou';
    case ExtensionsDeKarnak = 'extensions_karnak';

    /**
     * Le chantier du pharaon qui commandite cette mission.
     */
    public static function pour(string $pharaon): ?self
    {
        return match ($pharaon) {
            'Ahmôsis Ier' => self::PyramideDAbydos,
            'Thoutmôsis Ier' => self::ObelisquesDeKarnak,
            'Hatchepsout' => self::DeirElBahari,
            'Thoutmôsis III' => self::AkhMenou,
            'Amenhotep III' => self::TempleDeLouxor,
            'Akhenaton' => self::GrandTempleDAton,
            'Séthi Ier' => self::TempleDAbydos,
            'Ramsès III' => self::MedinetHabou,
            'Ramsès IV' => self::ExtensionsDeKarnak,
            default => null,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::PyramideDAbydos => 'la pyramide d\'Abydos',
            self::ObelisquesDeKarnak => 'les obélisques de Karnak',
            self::DeirElBahari => 'le temple de Deir el-Bahari',
            self::AkhMenou => 'l\'Akh-menou de Karnak',
            self::TempleDeLouxor => 'le temple de Louxor',
            self::GrandTempleDAton => 'le grand temple d\'Aton',
            self::TempleDAbydos => 'le temple d\'Abydos',
            self::MedinetHabou => 'le temple de Médinet Habou',
            self::ExtensionsDeKarnak => 'les extensions de Karnak',
        };
    }

    /**
     * Ce qu'on apprend en acceptant. Court — deux ou trois phrases, dit le
     * doc 09 — et vrai.
     */
    public function ceQuOnEnSait(): string
    {
        return match ($this) {
            self::PyramideDAbydos => 'Ahmôsis Ier fut le dernier pharaon à se faire élever une pyramide. Ce n\'était pas sa tombe mais un cénotaphe : on l\'y honorait sans l\'y enterrer.',
            self::ObelisquesDeKarnak => 'Thoutmôsis Ier dressa les premiers obélisques dans la cour du IVe pylône. Il fut aussi le premier à se faire creuser une tombe durable dans la Vallée des Rois.',
            self::DeirElBahari => 'Djeser-Djeserou, « la sublime des sublimes » : le temple à terrasses d\'Hatchepsout, adossé à la falaise. C\'est là qu\'elle fit graver le récit de son expédition vers Pount.',
            self::AkhMenou => 'La « salle des fêtes » de Thoutmôsis III, ajoutée à Karnak au retour de ses campagnes. Ses colonnes imitent les mâts d\'une tente royale.',
            self::TempleDeLouxor => 'Amenhotep III fit bâtir le temple de Louxor et dresser devant son temple funéraire les deux colosses que les Grecs prirent pour Memnon.',
            self::GrandTempleDAton => 'Akhenaton bâtit sa capitale en blocs de petite taille, les talatat, qu\'un seul homme pouvait porter. C\'est ce qui lui permit d\'élever une ville entière en quelques années.',
            self::TempleDAbydos => 'Le temple d\'Abydos porte la liste royale de Séthi Ier — soixante-seize noms de rois, qui reste l\'une de nos meilleures sources sur leur succession.',
            self::MedinetHabou => 'Le temple funéraire de Ramsès III, l\'un des mieux conservés d\'Égypte. Ses murs racontent la guerre contre les Peuples de la mer.',
            self::ExtensionsDeKarnak => 'Ramsès IV régna peu et bâtit beaucoup : il doubla les effectifs envoyés aux carrières pour tenir le rythme de ses chantiers à Karnak.',
        };
    }

    /**
     * La divinité que le monument honore : c'est elle qui gagne en faveur
     * quand la livraison est faite (doc 09).
     */
    public function divinite(): ?Divinite
    {
        return match ($this) {
            self::PyramideDAbydos, self::TempleDAbydos => Divinite::Osiris,
            self::ObelisquesDeKarnak, self::AkhMenou, self::DeirElBahari,
            self::TempleDeLouxor, self::MedinetHabou, self::ExtensionsDeKarnak => Divinite::AmonRe,
            // Akhenaton n'honore qu'Aton, absent du panthéon du jeu : sa quête
            // ne rapporte donc aucune faveur, et c'est historiquement juste.
            self::GrandTempleDAton => null,
        };
    }
}
