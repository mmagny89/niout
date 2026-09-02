<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La stèle réelle laissée par le pharaon qui commandite une mission (doc 09).
 *
 * « Chaque énigme de déchiffrage puise, quand c'est possible, dans le contenu
 * réel de la stèle associée au pharaon de la mission — résumé ou paraphrase du
 * sens général, **jamais une citation longue** —, présentée avec son vrai nom
 * et son lieu de découverte. »
 *
 * **Ce qu'on affiche est un résumé, jamais une traduction.** La contrainte est
 * de droits autant que d'honnêteté : le doc 09 la répète deux fois, et elle
 * vaut aussi pour les traductions anciennes tombées dans le domaine public.
 *
 * **La stèle n'est pas l'inscription qu'on déchiffre.** Les dalles du jeu
 * restent des **rébus** — signes vrais, combinaisons inventées —, et la stèle
 * est ce à quoi elles font écho. Les confondre laisserait croire au joueur
 * qu'il lit de l'égyptien, ce que le projet se refuse à laisser croire.
 *
 * Les cinq que le doc 09 ne donnait que pour « bien établies » ont été
 * vérifiées avant d'être nommées à l'écran, comme il le demandait lui-même.
 */
enum SteleHistorique: string
{
    case GrandeSteleDAhmosis = 'grande_stele_d_ahmosis';
    case SteleDeTombos = 'stele_de_tombos';
    case ReliefsDePount = 'reliefs_de_pount';
    case StelePoetique = 'stele_poetique';
    case InscriptionDuBatisseur = 'inscription_du_batisseur';
    case StelesFrontieresDAmarna = 'steles_frontieres_d_amarna';
    case SteleDeKanais = 'stele_de_kanais';
    case PapyrusHarris = 'papyrus_harris';
    case SteleDuOuadiHammamat = 'stele_du_ouadi_hammamat';

    public static function pourLePharaon(string $pharaon): ?self
    {
        return match ($pharaon) {
            'Ahmôsis Ier' => self::GrandeSteleDAhmosis,
            'Thoutmôsis Ier' => self::SteleDeTombos,
            'Hatchepsout' => self::ReliefsDePount,
            'Thoutmôsis III' => self::StelePoetique,
            'Amenhotep III' => self::InscriptionDuBatisseur,
            'Akhenaton' => self::StelesFrontieresDAmarna,
            'Séthi Ier' => self::SteleDeKanais,
            'Ramsès III' => self::PapyrusHarris,
            'Ramsès IV' => self::SteleDuOuadiHammamat,
            default => null,
        };
    }

    public function nom(): string
    {
        return match ($this) {
            self::GrandeSteleDAhmosis => 'La grande stèle d\'Ahmôsis',
            self::SteleDeTombos => 'La stèle de Tombos',
            self::ReliefsDePount => 'Les reliefs de l\'expédition vers Pount',
            self::StelePoetique => 'La stèle poétique de Thoutmôsis III',
            self::InscriptionDuBatisseur => 'La stèle des grands travaux',
            self::StelesFrontieresDAmarna => 'Les stèles-frontières d\'Akhetaton',
            self::SteleDeKanais => 'La stèle de Kanaïs',
            self::PapyrusHarris => 'Le grand papyrus Harris',
            self::SteleDuOuadiHammamat => 'La stèle de l\'Ouadi Hammamat',
        };
    }

    /**
     * Où on la trouve. Le doc 09 y tient : nommer le lieu ancre le contenu dans
     * un endroit réel, qu'on peut aller voir.
     */
    public function lieu(): string
    {
        return match ($this) {
            self::GrandeSteleDAhmosis => 'Karnak, dans le temple d\'Amon',
            self::SteleDeTombos => 'Gravée dans le rocher à Tombos, en amont de la troisième cataracte',
            self::ReliefsDePount => 'Deir el-Bahari, sur le portique sud de la terrasse médiane',
            self::StelePoetique => 'Karnak, aujourd\'hui au musée du Caire',
            self::InscriptionDuBatisseur => 'Kom el-Hettan, à l\'entrée de la cour solaire du temple funéraire',
            self::StelesFrontieresDAmarna => 'Seize stèles taillées dans les falaises qui entourent le site',
            self::SteleDeKanais => 'Ouadi Mia, sur la route du désert oriental',
            self::PapyrusHarris => 'Trouvé à Médinet Habou, aujourd\'hui au British Museum',
            self::SteleDuOuadiHammamat => 'Gravée dans la paroi de l\'ouadi, entre le Nil et la mer Rouge',
        };
    }

    /**
     * Ce que la pierre dit, **résumé**. Jamais une citation.
     */
    public function contenu(): string
    {
        return match ($this) {
            self::GrandeSteleDAhmosis => 'Le roi relève le pays après la guerre contre les Hyksôs : les temples sont rouverts, les offrandes rétablies, et l\'on rebâtit ce que le conflit avait laissé en ruine.',
            self::SteleDeTombos => 'À l\'an 2 du règne, le roi porte la frontière au-delà de la troisième cataracte et fait bâtir une forteresse à Tombos. Le texte est un hymne autant qu\'un compte rendu : il célèbre la victoire sur Kouch en termes appuyés.',
            self::ReliefsDePount => 'À l\'an 9, une flotte part vers Pount et en revient chargée d\'encens, d\'ébène, d\'ivoire, et d\'arbres à myrrhe vivants portés sur des perches. Le texte dit l\'expédition ordonnée par un oracle du dieu lui-même.',
            self::StelePoetique => 'Amon s\'adresse au roi et énumère les pays qu\'il lui a soumis. C\'est un poème de victoire plus qu\'un récit, et la campagne de Megiddo y tient sa place.',
            self::InscriptionDuBatisseur => 'Le roi énumère ce qu\'il a fait bâtir : un temple funéraire plus vaste qu\'aucun autre, ses portes plaquées d\'or, ses colosses de quartzite. La pierre est un inventaire de chantiers autant qu\'une prière.',
            self::StelesFrontieresDAmarna => 'Le décret de fondation d\'Akhetaton, daté de l\'an 5 : le roi délimite le site, dit pourquoi il le choisit, et jure de ne pas en franchir les bornes. Les plus tardives répondent aussi à ceux qui contestaient son règne.',
            self::SteleDeKanais => 'Le roi fait creuser un puits sur la route qui mène aux mines d\'or du désert oriental, et fonde un sanctuaire auprès. Le texte dit le souci des hommes qui mouraient de soif sur cette route.',
            self::PapyrusHarris => 'Le bilan d\'un règne entier, dressé à sa mort : tout ce que le roi a donné aux temples d\'Égypte — terres, troupeaux, or, hommes —, temple par temple. C\'est le plus long papyrus qui nous soit parvenu.',
            self::SteleDuOuadiHammamat => 'À l\'an 3, une expédition de plus de huit mille hommes part chercher la pierre de l\'ouadi. Le texte compte les carriers, les soldats, les porteurs d\'eau, et jusqu\'aux morts de la route.',
        };
    }

    /**
     * **Un papyrus n'est pas une stèle**, et l'écran ne doit pas le dire. Le
     * doc 09 le signale lui-même : « pas une stèle au sens strict, mais
     * document équivalent ».
     */
    public function estUneStele(): bool
    {
        return self::PapyrusHarris !== $this;
    }

    public function nature(): string
    {
        return $this->estUneStele() ? 'stèle' : 'papyrus';
    }
}
