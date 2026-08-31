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
        };
    }

    /**
     * Les inscriptions que cette ville peut tenter : celles dont elle lit tous
     * les signes, et qu'elle n'a pas encore déchiffrées.
     *
     * @return list<self>
     */
    public static function disponiblesPour(City $ville): array
    {
        $faites = $ville->inscriptionsDechiffrees();
        $disponibles = [];

        foreach (self::cases() as $inscription) {
            if (\in_array($inscription, $faites, true) || !$inscription->estLisiblePar($ville)) {
                continue;
            }

            $disponibles[] = $inscription;
        }

        return $disponibles;
    }

    public function estLisiblePar(City $ville): bool
    {
        foreach ($this->signes() as $signe) {
            if (!CleDeLecture::sait($ville, $signe)) {
                return false;
            }
        }

        return true;
    }
}
