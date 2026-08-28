<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La spécialité d'un chef de bâtiment (doc 03).
 *
 * Elle s'ajoute aux traits génériques et dit **quel aspect** de la production
 * est renforcé, là où la compétence n'en dit que l'ampleur. Elle est **tirée au
 * sort**, jamais choisie : un candidat est un profil complet reçu par l'offre
 * d'emploi, pas un employé qu'on façonne après coup.
 *
 * Trois bâtiments n'en ont aucune — la Résidence familiale, le Quartier
 * d'habitation et l'Auberge —, le doc 03 n'en listant pas pour eux.
 *
 * La plupart de ces spécialités sont **générées mais inertes** : seules celles
 * du Grenier, du Marché et du Port portent sur une production qui existe déjà
 * (lot 4.8). Les autres attendent leur phase, et l'interface doit le dire.
 */
enum SpecialiteDeChef: string
{
    case AtelierPoterie = 'atelier_poterie';
    case AtelierPapyrus = 'atelier_papyrus';
    case AtelierVannerie = 'atelier_vannerie';
    case AtelierTissus = 'atelier_tissus';
    case AtelierBierePain = 'atelier_biere_pain';

    case EntrepotNegociateur = 'entrepot_negociateur';
    case EntrepotLogisticien = 'entrepot_logisticien';

    case MarcheAcheteur = 'marche_acheteur';
    case MarcheVendeur = 'marche_vendeur';

    case ForgeArmurier = 'forge_armurier';
    case ForgeOutilleur = 'forge_outilleur';

    case TempleDevot = 'temple_devot';

    case GrenierGestionnaire = 'grenier_gestionnaire';

    case ScribesDechiffreur = 'scribes_dechiffreur';
    case ScribesOraculaire = 'scribes_oraculaire';

    case CaserneInstructeurArcher = 'caserne_instructeur_archer';
    case CaserneInstructeurBouclier = 'caserne_instructeur_bouclier';

    case PortPecheur = 'port_pecheur';
    case PortCommercantNaval = 'port_commercant_naval';

    public function libelle(): string
    {
        return match ($this) {
            self::AtelierPoterie => 'Potier',
            self::AtelierPapyrus => 'Papyrussier',
            self::AtelierVannerie => 'Vannier',
            self::AtelierTissus => 'Tisserand',
            self::AtelierBierePain => 'Brasseur',
            self::EntrepotNegociateur => 'Négociateur',
            self::EntrepotLogisticien => 'Logisticien',
            self::MarcheAcheteur => 'Acheteur',
            self::MarcheVendeur => 'Vendeur',
            self::ForgeArmurier => 'Armurier',
            self::ForgeOutilleur => 'Outilleur',
            self::TempleDevot => 'Dévot',
            self::GrenierGestionnaire => 'Gestionnaire rigoureux',
            self::ScribesDechiffreur => 'Déchiffreur',
            self::ScribesOraculaire => 'Oraculaire',
            self::CaserneInstructeurArcher => 'Instructeur des archers',
            self::CaserneInstructeurBouclier => 'Instructeur des porteurs de bouclier',
            self::PortPecheur => 'Pêcheur',
            self::PortCommercantNaval => 'Commerçant naval',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AtelierPoterie => 'Rend la poterie plus abondante.',
            self::AtelierPapyrus => 'Rend le papyrus plus abondant.',
            self::AtelierVannerie => 'Rend la vannerie et les sandales plus abondantes.',
            self::AtelierTissus => 'Rend les tissus plus abondants.',
            self::AtelierBierePain => 'Rend la bière et le pain plus abondants.',
            self::EntrepotNegociateur => 'Obtient de meilleurs prix des caravanes.',
            self::EntrepotLogisticien => 'Raccourcit les trajets de caravane.',
            self::MarcheAcheteur => 'Achète moins cher.',
            self::MarcheVendeur => 'Vend plus cher.',
            self::ForgeArmurier => 'Forge de meilleures armes.',
            self::ForgeOutilleur => 'Forge de meilleurs outils.',
            self::TempleDevot => 'Attire davantage la faveur d\'une divinité.',
            self::GrenierGestionnaire => 'Perd moins de grain à la conservation.',
            self::ScribesDechiffreur => 'Déchiffre les inscriptions plus vite.',
            self::ScribesOraculaire => 'Perce les devinettes et les oracles plus vite.',
            self::CaserneInstructeurArcher => 'Forme de meilleurs archers.',
            self::CaserneInstructeurBouclier => 'Forme de meilleurs porteurs de bouclier.',
            self::PortPecheur => 'Ramène davantage de poisson.',
            self::PortCommercantNaval => 'Écoule davantage par voie d\'eau.',
        };
    }

    /**
     * Les spécialités possibles pour diriger ce bâtiment, à parts égales
     * (doc 03). Une liste vide signifie que le poste n'en comporte aucune.
     *
     * @return list<self>
     */
    public static function pour(TypeDeBatiment $batiment): array
    {
        return match ($batiment) {
            TypeDeBatiment::Atelier => [
                self::AtelierPoterie, self::AtelierPapyrus, self::AtelierVannerie,
                self::AtelierTissus, self::AtelierBierePain,
            ],
            TypeDeBatiment::Entrepot => [self::EntrepotNegociateur, self::EntrepotLogisticien],
            TypeDeBatiment::Marche => [self::MarcheAcheteur, self::MarcheVendeur],
            TypeDeBatiment::Forge => [self::ForgeArmurier, self::ForgeOutilleur],
            TypeDeBatiment::Temple => [self::TempleDevot],
            TypeDeBatiment::Grenier => [self::GrenierGestionnaire],
            TypeDeBatiment::MaisonDesScribes => [self::ScribesDechiffreur, self::ScribesOraculaire],
            TypeDeBatiment::Caserne => [self::CaserneInstructeurArcher, self::CaserneInstructeurBouclier],
            TypeDeBatiment::Port => [self::PortPecheur, self::PortCommercantNaval],
            TypeDeBatiment::ResidenceFamiliale, TypeDeBatiment::QuartierDHabitation, TypeDeBatiment::Auberge => [],
        };
    }

    /**
     * Spécialités dont l'effet existe réellement aujourd'hui — celles des trois
     * bâtiments qui produisent déjà quelque chose (lot 4.8). Les autres sont
     * tirées et affichées, mais dorment jusqu'à leur phase.
     */
    public function agitDeja(): bool
    {
        return \in_array($this, [
            self::GrenierGestionnaire,
            self::MarcheAcheteur,
            self::MarcheVendeur,
            self::PortPecheur,
        ], true);
    }
}
