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
 * Chaque indice a une **source** : le terrain, qu'un éclaireur fouille, ou un
 * témoignage, qu'un émissaire recueille (doc 04, doc 10).
 *
 * **Chaque fil rouge porte quatre indices** : trois concordants — le compte
 * exact qu'il faut pour conclure — et un quatrième qui égare ou qui éclaire.
 * On ne peut donc pas se contenter de ramasser : il faut lire.
 */
enum Indice: string
{
    // Mission 1 — le passage coupé.
    case BorneRenversee = 'borne_renversee';
    case TracesDeCampement = 'traces_de_campement';
    case DigueRompue = 'digue_rompue';
    case OstraconDeGarnison = 'ostracon_de_garnison';
    case VieuxPuitsATarir = 'vieux_puits_a_tarir';

    // Mission 2 — les bornes déplacées.
    case CadastreRecopie = 'cadastre_recopie';
    case BorneAuSolFrais = 'borne_au_sol_frais';
    case ChampTropGrand = 'champ_trop_grand';
    case PlainteDunPaysan = 'plainte_dun_paysan';

    // Mission 3 — la flotte qui ne part pas.
    case BoisQuiSuinte = 'bois_qui_suinte';
    case RefusDuCharpentier = 'refus_du_charpentier';
    case CoqueFendue = 'coque_fendue';
    case ColereDeLequipage = 'colere_de_lequipage';

    // Mission 4 — la porte laissée ouverte.
    case RondesPrevues = 'rondes_prevues';
    case ScribeTropRiche = 'scribe_trop_riche';
    case TessonAuNomEtranger = 'tesson_au_nom_etranger';
    case MurmureDeGarnison = 'murmure_de_garnison';

    // Mission 5 — le chantier qui n'avance pas.
    case BlocsComptesDeuxFois = 'blocs_comptes_deux_fois';
    case OrnieresNocturnes = 'ornieres_nocturnes';
    case RegistreRature = 'registre_rature';
    case PierreQuiSeDelite = 'pierre_qui_se_delite';

    // Mission 6 — l'eau qui manque.
    case PuitsAnciens = 'puits_anciens';
    case SableHumideAuPied = 'sable_humide_au_pied';
    case SondageSec = 'sondage_sec';
    case DireDunBerger = 'dire_dun_berger';

    // Mission 7 — l'or qui s'évapore.
    case PoidsEbreche = 'poids_ebreche';
    case EcartDesTablettes = 'ecart_des_tablettes';
    case MaisonDuPeseur = 'maison_du_peseur';
    case RecitDuBatelier = 'recit_du_batelier';

    // Mission 8 — le canal envasé.
    case PriseDeauNeuve = 'prise_deau_neuve';
    case NiveauQuiTombe = 'niveau_qui_tombe';
    case VaseTropRecente = 'vase_trop_recente';
    case ColereDesCureurs = 'colere_des_cureurs';

    // Mission 9 — les hommes qui désertent.
    case CiterneSaumatre = 'citerne_saumatre';
    case RationsGatees = 'rations_gatees';
    case ListeDesAbsents = 'liste_des_absents';
    case PeurDesBandits = 'peur_des_bandits';

    // Mission 10 — la galerie effondrée.
    case PiliersTropMinces = 'piliers_trop_minces';
    case FilonSuiviTropLoin = 'filon_suivi_trop_loin';
    case BoisDeSoutienAbsent = 'bois_de_soutien_absent';
    case PresageDuTemple = 'presage_du_temple';

    // Les secondaires, communes à toutes les régions.
    case OutilsRouilles = 'outils_rouilles';
    case FilonEpuise = 'filon_epuise';
    case MarqueDeCrue = 'marque_de_crue';
    case RecitDuPremierCaravanier = 'recit_du_premier_caravanier';
    case RecitDuSecond = 'recit_du_second';
    case RegistreDuPeage = 'registre_du_peage';

    // La malversation du rival.
    case DoubleJeuDePoids = 'double_jeu_de_poids';
    case TablettesDuRival = 'tablettes_du_rival';
    case PlainteDunPorteur = 'plainte_dun_porteur';

    public function enquete(): Enquete
    {
        return match ($this) {
            self::BorneRenversee, self::TracesDeCampement, self::DigueRompue,
            self::OstraconDeGarnison, self::VieuxPuitsATarir => Enquete::PassageCoupe,
            self::CadastreRecopie, self::BorneAuSolFrais, self::ChampTropGrand,
            self::PlainteDunPaysan => Enquete::BornesDeplacees,
            self::BoisQuiSuinte, self::RefusDuCharpentier, self::CoqueFendue,
            self::ColereDeLequipage => Enquete::FlotteQuiNePartPas,
            self::RondesPrevues, self::ScribeTropRiche, self::TessonAuNomEtranger,
            self::MurmureDeGarnison => Enquete::PorteLaisseeOuverte,
            self::BlocsComptesDeuxFois, self::OrnieresNocturnes, self::RegistreRature,
            self::PierreQuiSeDelite => Enquete::ChantierQuiNavancePas,
            self::PuitsAnciens, self::SableHumideAuPied, self::SondageSec,
            self::DireDunBerger => Enquete::EauQuiManque,
            self::PoidsEbreche, self::EcartDesTablettes, self::MaisonDuPeseur,
            self::RecitDuBatelier => Enquete::OrQuiSevapore,
            self::PriseDeauNeuve, self::NiveauQuiTombe, self::VaseTropRecente,
            self::ColereDesCureurs => Enquete::CanalEnvase,
            self::CiterneSaumatre, self::RationsGatees, self::ListeDesAbsents,
            self::PeurDesBandits => Enquete::HommesQuiDesertent,
            self::PiliersTropMinces, self::FilonSuiviTropLoin, self::BoisDeSoutienAbsent,
            self::PresageDuTemple => Enquete::GalerieEffondree,
            self::OutilsRouilles, self::FilonEpuise, self::MarqueDeCrue => Enquete::CarrieresAbandonnees,
            self::RecitDuPremierCaravanier, self::RecitDuSecond,
            self::RegistreDuPeage => Enquete::RumeurDeLaCaravane,
            self::DoubleJeuDePoids, self::TablettesDuRival,
            self::PlainteDunPorteur => Enquete::MalversationDuRival,
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

            self::CadastreRecopie => 'Le cadastre a été recopié il y a peu : l\'encre est fraîche sous une main qui n\'est pas celle du scribe royal.',
            self::BorneAuSolFrais => 'Une borne de granit, bien plantée — dans une terre retournée de la saison dernière.',
            self::ChampTropGrand => 'Un champ mesure trois cents coudées de plus que ce que le registre lui donne.',
            self::PlainteDunPaysan => 'Un paysan jure que le fleuve a tout bougé, et refuse d\'en dire davantage.',

            self::BoisQuiSuinte => 'Les madriers livrés suintent encore : ce bois n\'a pas séché une saison.',
            self::RefusDuCharpentier => 'Le maître charpentier refuse de signer la coque, et ne s\'en explique qu\'à demi-mot.',
            self::CoqueFendue => 'Une coque montée le mois dernier s\'est fendue à quai, sans avoir pris la mer.',
            self::ColereDeLequipage => 'L\'équipage réclame une solde qu\'on lui a pourtant versée : le registre le dit.',

            self::RondesPrevues => 'Une attaque a eu lieu entre deux rondes, à l\'heure exacte où le mur était vide.',
            self::ScribeTropRiche => 'Le scribe de la garnison porte du lin fin et paie en cuivre neuf. Sa solde ne le permet pas.',
            self::TessonAuNomEtranger => 'Un tesson gravé d\'un nom cananéen, retrouvé dans le poste de garde.',
            self::MurmureDeGarnison => 'On murmure qu\'un officier cananéen resté en poste renseignerait les siens.',

            self::BlocsComptesDeuxFois => 'Le même numéro de bloc revient deux fois dans le registre, à quinze jours d\'écart.',
            self::OrnieresNocturnes => 'Des ornières de traîneau vers le sud, alors que le chantier est au nord.',
            self::RegistreRature => 'Trois lignes grattées puis réécrites, toutes de la même main.',
            self::PierreQuiSeDelite => 'Un bloc se délite au burin : celui-là vient d\'un mauvais banc.',

            self::PuitsAnciens => 'Deux margelles anciennes, au pied de la falaise, à une heure de marche du site.',
            self::SableHumideAuPied => 'Le sable reste humide sous la main, au petit matin, dans le creux du ouadi.',
            self::SondageSec => 'Le sondage creusé sur le plateau descend de vingt coudées et ne donne rien.',
            self::DireDunBerger => 'Un berger dit que la crue a été faible et que la nappe est basse partout.',

            self::PoidsEbreche => 'Le poids de dix deben du péage est ébréché : il pèse moins qu\'il ne dit.',
            self::EcartDesTablettes => 'L\'écart entre le chargement au départ et à l\'arrivée revient à chaque convoi, toujours dans le même sens.',
            self::MaisonDuPeseur => 'La maison du peseur s\'est agrandie de deux pièces cette année.',
            self::RecitDuBatelier => 'Un batelier raconte qu\'on détrousse les convois à la cataracte. Il n\'était pas du voyage.',

            self::PriseDeauNeuve => 'Une prise d\'eau creusée en amont, large et récente, qu\'aucun registre ne mentionne.',
            self::NiveauQuiTombe => 'Le niveau du canal a chuté d\'un coup, pas d\'une saison à l\'autre.',
            self::VaseTropRecente => 'La vase du fond est molle et neuve : elle ne s\'est pas déposée en dix ans.',
            self::ColereDesCureurs => 'Les cureurs disent que les crocodiles les empêchent de travailler.',

            self::CiterneSaumatre => 'L\'eau de la grande citerne a un goût de sel, et laisse un dépôt.',
            self::RationsGatees => 'Deux jarres de grain sur trois sont moisies avant d\'être ouvertes.',
            self::ListeDesAbsents => 'La liste des absents s\'allonge après chaque distribution, jamais après une alerte.',
            self::PeurDesBandits => 'On raconte au camp que des bandits du ouadi enlèvent les traînards.',

            self::PiliersTropMinces => 'Les piliers laissés dans la galerie voisine sont deux fois plus minces qu\'ailleurs.',
            self::FilonSuiviTropLoin => 'Le front suit le filon en biais sur trente coudées, sans jamais élargir.',
            self::BoisDeSoutienAbsent => 'Aucun bois de soutènement dans les décombres : il n\'y en avait pas.',
            self::PresageDuTemple => 'Le prêtre d\'Hathor rappelle qu\'on n\'a rien offert depuis deux saisons.',

            self::OutilsRouilles => 'Des ciseaux de cuivre laissés en place, encore fichés dans la pierre.',
            self::FilonEpuise => 'Le front de taille bute sur du calcaire stérile : il n\'y avait plus rien à prendre.',
            self::MarqueDeCrue => 'Une marque de crue haute sur la paroi, bien au-dessus des cabanes.',
            self::RecitDuPremierCaravanier => 'Le premier jure que la piste est sûre, et qu\'il l\'a prise le mois dernier.',
            self::RecitDuSecond => 'Le second jure le contraire, et décrit un ouadi que le premier n\'a pas nommé.',
            self::RegistreDuPeage => 'Le registre du péage ne porte aucun passage depuis deux quinzaines.',

            self::DoubleJeuDePoids => 'Deux jeux de poids dans le même coffre, et ils ne s\'accordent pas.',
            self::TablettesDuRival => 'Ses tablettes déclarent au péage moins qu\'il ne charge : l\'écart revient à chaque ligne.',
            self::PlainteDunPorteur => 'Un porteur se plaint d\'avoir été payé en grain gâté. Il en veut à tout le monde.',
        };
    }

    public function nature(): NatureDIndice
    {
        return match ($this) {
            self::BorneRenversee, self::TracesDeCampement, self::DigueRompue,
            self::CadastreRecopie, self::BorneAuSolFrais, self::ChampTropGrand,
            self::BoisQuiSuinte, self::RefusDuCharpentier, self::CoqueFendue,
            self::RondesPrevues, self::ScribeTropRiche, self::TessonAuNomEtranger,
            self::BlocsComptesDeuxFois, self::OrnieresNocturnes, self::RegistreRature,
            self::PuitsAnciens, self::SableHumideAuPied, self::SondageSec,
            self::PoidsEbreche, self::EcartDesTablettes, self::MaisonDuPeseur,
            self::PriseDeauNeuve, self::NiveauQuiTombe, self::VaseTropRecente,
            self::CiterneSaumatre, self::RationsGatees, self::ListeDesAbsents,
            self::PiliersTropMinces, self::FilonSuiviTropLoin, self::BoisDeSoutienAbsent,
            self::OutilsRouilles, self::FilonEpuise,
            self::RecitDuSecond, self::RegistreDuPeage,
            self::DoubleJeuDePoids, self::TablettesDuRival => NatureDIndice::Concordant,

            self::VieuxPuitsATarir, self::MarqueDeCrue => NatureDIndice::Optionnel,

            default => NatureDIndice::Trompeur,
        };
    }

    /**
     * D'où il vient. Le terrain se fouille, le témoignage se recueille.
     */
    public function source(): SourceDIndice
    {
        return match ($this) {
            self::OstraconDeGarnison, self::PlainteDunPaysan, self::RefusDuCharpentier,
            self::ColereDeLequipage, self::MurmureDeGarnison, self::ScribeTropRiche,
            self::DireDunBerger, self::RecitDuBatelier, self::ColereDesCureurs,
            self::PeurDesBandits, self::PresageDuTemple, self::MaisonDuPeseur,
            self::RecitDuPremierCaravanier, self::RecitDuSecond, self::RegistreDuPeage,
            self::PlainteDunPorteur => SourceDIndice::Temoignage,
            default => SourceDIndice::Terrain,
        };
    }
}
