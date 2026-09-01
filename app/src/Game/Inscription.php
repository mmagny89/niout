<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Les inscriptions à déchiffrer (doc 10).
 *
 * **Ce qui est vrai et ce qui est du jeu, il faut le dire nettement.** Les
 * signes sont réels, leurs sens sont réels (`SymboleHieroglyphique`) ; les
 * **combinaisons**, elles, sont du jeu. Ce ne sont pas des phrases d'égyptien
 * — l'écriture égyptienne a une grammaire, des phonogrammes et des
 * déterminatifs qu'on n'enseigne pas ici. Ce sont des **indices en rébus**,
 * exactement comme l'exemple du doc 10 : « eau + route + danger » pour dire
 * qu'un passage du fleuve est coupé.
 *
 * La distinction n'est pas cosmétique : elle dit ce que le joueur apprend
 * vraiment (des signes et leur sens) et ce qu'il ne doit pas croire avoir
 * appris (lire l'égyptien).
 *
 * Une inscription n'est proposée que si la ville sait lire **tous** ses
 * signes : une énigme insoluble faute de clé serait un mur, pas une énigme.
 */
enum Inscription: string
{
    case HommeVenuALaMaison = 'homme_venu_a_la_maison';
    case RouteLeLongDuFleuve = 'route_le_long_du_fleuve';
    case MarcheJusquAuDesert = 'marche_jusqu_au_desert';
    case BateauDunAutrePays = 'bateau_dun_autre_pays';
    case LePainDeLaMaisonnee = 'le_pain_de_la_maisonnee';
    case OrDuDesert = 'or_du_desert';
    case ParoleDevantLeVisage = 'parole_devant_le_visage';
    case LeCanalAuSoleil = 'le_canal_au_soleil';
    case VieDansLEnceinteDuDieu = 'vie_dans_l_enceinte_du_dieu';

    /**
     * Les deux inscriptions du fil rouge de la mission 1 : celle qu'Ahmôsis
     * fait porter avec sa commande, et celle qu'on grave une fois la route
     * rouverte. Elles ne se proposent pas comme les autres — voir `FilRouge`.
     */
    case CommandeDAhmosis = 'commande_d_ahmosis';
    case LaRouteEstRouverte = 'la_route_est_rouverte';

    case OuvertureDeSai = 'ouverture_de_sai';
    case SaiEstFondee = 'sai_est_fondee';
    case OuvertureDeMersa = 'ouverture_de_mersa';
    case LaFlotteEstPartie = 'la_flotte_est_partie';
    case OuvertureDeMegiddo = 'ouverture_de_megiddo';
    case MegiddoEstTenue = 'megiddo_est_tenue';
    case OuvertureDeMalkata = 'ouverture_de_malkata';
    case MalkataSeDresse = 'malkata_se_dresse';
    case OuvertureDAkhetaton = 'ouverture_d_akhetaton';
    case AkhetatonSort = 'akhetaton_sort';
    case OuvertureDElephantine = 'ouverture_d_elephantine';
    case ElephantineCompte = 'elephantine_compte';
    case OuvertureDeShedet = 'ouverture_de_shedet';
    case ShedetRespire = 'shedet_respire';
    case OuvertureDuOuadi = 'ouverture_du_ouadi';
    case LeOuadiRend = 'le_ouadi_rend';
    case OuvertureDuSinai = 'ouverture_du_sinai';
    case LeSinaiRend = 'le_sinai_rend';

    /**
     * Les signes, **dans l'ordre où ils sont gravés**. C'est cet ordre que le
     * joueur doit retrouver.
     *
     * @return list<SymboleHieroglyphique>
     */
    public function signes(): array
    {
        return match ($this) {
            self::HommeVenuALaMaison => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Maison,
            ],
            self::RouteLeLongDuFleuve => [
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Marcher,
            ],
            self::MarcheJusquAuDesert => [
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Desert,
            ],
            self::BateauDunAutrePays => [
                SymboleHieroglyphique::Bateau,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Pays,
            ],
            self::LePainDeLaMaisonnee => [
                SymboleHieroglyphique::Pain,
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Femme,
            ],
            self::OrDuDesert => [
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Desert,
                SymboleHieroglyphique::Or,
            ],
            self::ParoleDevantLeVisage => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Bouche,
                SymboleHieroglyphique::Visage,
            ],
            self::LeCanalAuSoleil => [
                SymboleHieroglyphique::Soleil,
                SymboleHieroglyphique::Canal,
                SymboleHieroglyphique::Roseau,
            ],
            self::VieDansLEnceinteDuDieu => [
                SymboleHieroglyphique::Dieu,
                SymboleHieroglyphique::Enceinte,
                SymboleHieroglyphique::Vie,
            ],
            // L'acte I : trois signes connus d'emblée, pour que la première
            // inscription soit lisible avant d'avoir rien bâti.
            self::CommandeDAhmosis => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Eau,
            ],
            // L'acte III : cinq signes, dont trois qui demandent une Maison
            // des scribes déjà montée.
            self::LaRouteEstRouverte => [
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Pays,
                SymboleHieroglyphique::Pain,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDeSai => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Marcher,
            ],
            self::SaiEstFondee => [
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Pays,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Vie,
                SymboleHieroglyphique::Dieu,
            ],
            self::OuvertureDeMersa => [
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Homme,
            ],
            self::LaFlotteEstPartie => [
                SymboleHieroglyphique::Bateau,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Pain,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDeMegiddo => [
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Eau,
            ],
            self::MegiddoEstTenue => [
                SymboleHieroglyphique::Enceinte,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Pays,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDeMalkata => [
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Homme,
            ],
            self::MalkataSeDresse => [
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Or,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Soleil,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDAkhetaton => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Eau,
            ],
            self::AkhetatonSort => [
                SymboleHieroglyphique::Soleil,
                SymboleHieroglyphique::Pays,
                SymboleHieroglyphique::Maison,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDElephantine => [
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Maison,
            ],
            self::ElephantineCompte => [
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Or,
                SymboleHieroglyphique::Panier,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Maison,
            ],
            self::OuvertureDeShedet => [
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Maison,
            ],
            self::ShedetRespire => [
                SymboleHieroglyphique::Canal,
                SymboleHieroglyphique::Eau,
                SymboleHieroglyphique::Pain,
                SymboleHieroglyphique::Pays,
                SymboleHieroglyphique::Vie,
            ],
            self::OuvertureDuOuadi => [
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Maison,
            ],
            self::LeOuadiRend => [
                SymboleHieroglyphique::Desert,
                SymboleHieroglyphique::Route,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Pain,
                SymboleHieroglyphique::Or,
            ],
            self::OuvertureDuSinai => [
                SymboleHieroglyphique::Marcher,
                SymboleHieroglyphique::Homme,
                SymboleHieroglyphique::Eau,
            ],
            self::LeSinaiRend => [
                SymboleHieroglyphique::Desert,
                SymboleHieroglyphique::Enceinte,
                SymboleHieroglyphique::Dieu,
                SymboleHieroglyphique::Or,
                SymboleHieroglyphique::Vie,
            ],
        };
    }

    /**
     * Ce que l'inscription dit, une fois les signes remis dans l'ordre. C'est
     * la récompense de lecture : le joueur voit le sens sortir des images.
     */
    public function lecture(): string
    {
        return match ($this) {
            self::HommeVenuALaMaison => 'Un homme est venu jusqu\'à la maison.',
            self::RouteLeLongDuFleuve => 'La route suit le fleuve : on y marche sans se perdre.',
            self::MarcheJusquAuDesert => 'Depuis l\'eau, la marche mène au désert.',
            self::BateauDunAutrePays => 'Un bateau est venu par le fleuve, d\'un autre pays.',
            self::LePainDeLaMaisonnee => 'Le pain de la maison est à celle qui la tient.',
            self::OrDuDesert => 'Une route s\'enfonce au désert, vers l\'or.',
            self::ParoleDevantLeVisage => 'L\'homme a parlé, en face.',
            self::LeCanalAuSoleil => 'Au soleil, le canal fait lever les roseaux.',
            self::VieDansLEnceinteDuDieu => 'Dans l\'enceinte du dieu se tient la vie.',
            self::CommandeDAhmosis => 'Que des hommes aillent de nouveau par l\'eau.',
            self::LaRouteEstRouverte => 'La route est reprise : on va par le pays, le pain revient, et le pays vit.',
            self::OuvertureDeSai => 'Que des hommes quittent la maison et aillent au loin.',
            self::SaiEstFondee => 'La maison tient dans le pays : les hommes y vivent, et le dieu y a sa place.',
            self::OuvertureDeMersa => 'Que l\'on aille par l\'eau, et que des hommes en reviennent.',
            self::LaFlotteEstPartie => 'Le bateau va sur l\'eau par sa route ; le pain revient, et la vie avec.',
            self::OuvertureDeMegiddo => 'La maison est à nous, et les hommes de l\'eau y viendront.',
            self::MegiddoEstTenue => 'L\'enceinte tient, les hommes gardent la route, et le pays vit.',
            self::OuvertureDeMalkata => 'Au bord de l\'eau, une maison pour les hommes du roi.',
            self::MalkataSeDresse => 'La maison d\'or au bord de l\'eau, sous le soleil, pour la vie.',
            self::OuvertureDAkhetaton => 'Des hommes iront jusqu\'à l\'eau, et là ils s\'arrêteront.',
            self::AkhetatonSort => 'Le soleil sur le pays : une maison, des hommes, et la vie.',
            self::OuvertureDElephantine => 'Ce qui vient par l\'eau passe et entre à la maison.',
            self::ElephantineCompte => 'Ce qui descend l\'eau, l\'or compris, se compte et entre à la maison.',
            self::OuvertureDeShedet => 'L\'eau, les hommes, la maison : que les trois tiennent ensemble.',
            self::ShedetRespire => 'Le canal rend l\'eau, le pain revient, le pays vit.',
            self::OuvertureDuOuadi => 'Des hommes marcheront loin de toute maison.',
            self::LeOuadiRend => 'Au désert, par la route, des hommes, du pain — et l\'or au bout.',
            self::OuvertureDuSinai => 'On ira, les hommes porteront, et l\'eau manquera.',
            self::LeSinaiRend => 'Au désert, l\'enceinte du dieu : l\'or en sort, et la vie tient.',
        };
    }

    /**
     * Ce qu'on voit avant de déchiffrer : d'où vient la pierre. Sans ce
     * cadrage, une inscription n'est qu'un exercice ; avec lui, c'est une
     * trouvaille.
     */
    public function provenance(): string
    {
        return match ($this) {
            self::HommeVenuALaMaison => 'Une brique marquée au poinçon, ramassée près de la Résidence.',
            self::RouteLeLongDuFleuve => 'Une borne de chemin, à demi ensablée.',
            self::MarcheJusquAuDesert => 'Un éclat de calcaire gravé, laissé par un caravanier.',
            self::BateauDunAutrePays => 'Une planche de barque, repêchée sur la berge.',
            self::LePainDeLaMaisonnee => 'Un tesson de jarre à provisions.',
            self::OrDuDesert => 'Une stèle basse, à l\'entrée d\'un ouadi.',
            self::ParoleDevantLeVisage => 'Un ostracon, de ceux dont les scribes se servent pour tout.',
            self::LeCanalAuSoleil => 'Une margelle de bassin, usée par les seaux.',
            self::VieDansLEnceinteDuDieu => 'Un linteau tombé d\'un mur d\'enceinte.',
            self::CommandeDAhmosis => 'La tablette scellée qu\'un messager du roi a déposée avec sa commande.',
            self::LaRouteEstRouverte => 'La stèle que vos scribes viennent de graver, et qu\'il faut relire avant de la dresser.',
            self::OuvertureDeSai => 'La tablette qu\'un messager de Thoutmôsis a portée jusqu\'à l\'île.',
            self::SaiEstFondee => 'La stèle de fondation, prête à être dressée sur l\'île.',
            self::OuvertureDeMersa => 'Une planchette scellée, déposée sur le quai vide.',
            self::LaFlotteEstPartie => 'La stèle du quai, gravée au retour de la première flotte.',
            self::OuvertureDeMegiddo => 'Un ordre gravé, cloué à la porte de la citadelle.',
            self::MegiddoEstTenue => 'Le linteau de la porte reconstruite.',
            self::OuvertureDeMalkata => 'Une tablette d\'argile, posée sur le premier piquet du chantier.',
            self::MalkataSeDresse => 'La dédicace du palais, à relire avant la cérémonie.',
            self::OuvertureDAkhetaton => 'Une stèle de bornage plantée dans le sable nu.',
            self::AkhetatonSort => 'La grande stèle de limite, taillée dans la falaise.',
            self::OuvertureDElephantine => 'Une tablette du bureau des douanes, tendue à votre arrivée.',
            self::ElephantineCompte => 'La borne du poste douanier, refaite après l\'affaire.',
            self::OuvertureDeShedet => 'Un ostracon glissé sous la porte du temple de Sobek.',
            self::ShedetRespire => 'La margelle du bassin, regravée par les prêtres.',
            self::OuvertureDuOuadi => 'Une planchette de bois, clouée au premier campement.',
            self::LeOuadiRend => 'Un rocher gravé à l\'entrée du ouadi, comme les expéditions en laissaient.',
            self::OuvertureDuSinai => 'Un ostracon laissé par l\'expédition précédente.',
            self::LeSinaiRend => 'La stèle votive du temple d\'Hathor, à relire avant l\'offrande.',
        };
    }

    /**
     * Les inscriptions que cette ville peut tenter : celles dont elle lit tous
     * les signes, et qu'elle n'a pas encore déchiffrées.
     *
     * @return list<self>
     */
    public static function disponiblesPour(City $ville, int $cycle = 0): array
    {
        $faites = $ville->inscriptionsDechiffrees();
        $disponibles = [];

        foreach (self::cases() as $inscription) {
            if (\in_array($inscription, $faites, true) || !$inscription->estLisiblePar($ville, $cycle)) {
                continue;
            }

            $disponibles[] = $inscription;
        }

        return $disponibles;
    }

    public function estLisiblePar(City $ville, int $cycle = 0): bool
    {
        foreach ($this->signes() as $signe) {
            if (!CleDeLecture::sait($ville, $signe, $cycle)) {
                return false;
            }
        }

        return true;
    }
}
