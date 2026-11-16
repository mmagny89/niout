<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un fait de la quinzaine, tel que la trace le garde (`FaitDeMonde`).
 *
 * **Un fait est structuré, jamais un texte** : le texte se compose à la lecture,
 * à partir du type, du lieu et de la quantité. Un texte stocké divergerait du
 * fait qu'il raconte — le récapitulatif de la quinzaine l'a déjà appris.
 *
 * Les faits se **déduisent de deux photographies** de la ville, avant et après
 * (`DifferenceDeQuinzaine`), sans que les services du cycle aient à les
 * annoncer : c'est la méthode du récapitulatif, et pour la même raison.
 */
enum TypeDeFait: string
{
    case ChantierAvance = 'chantier_avance';
    case ChantierAcheve = 'chantier_acheve';
    case FabricationAvance = 'fabrication_avance';
    case FabricationAchevee = 'fabrication_achevee';
    case ConvoiParti = 'convoi_parti';
    case ConvoiRentre = 'convoi_rentre';
    case ExpeditionEnRoute = 'expedition_en_route';
    case ExpeditionRentree = 'expedition_rentree';
    case EpidemieEclate = 'epidemie_eclate';
    case EpidemieFinie = 'epidemie_finie';
    case ChefParti = 'chef_parti';
    case RivalArrive = 'rival_arrive';
    case RivalParti = 'rival_parti';
    case MedjayBlesse = 'medjay_blesse';
    case Fete = 'fete';

    /**
     * La phrase du fait, au présent, pour le journal d'un jour.
     *
     * @param array<string, mixed> $details
     */
    public function phrase(?string $lieu, ?int $quantite, array $details): string
    {
        $lieu ??= 'la ville';

        return match ($this) {
            self::ChantierAvance => \sprintf('Le chantier du %s avance.', $lieu),
            self::ChantierAcheve => \sprintf('Le chantier du %s s\'achève.', $lieu),
            self::FabricationAvance => \sprintf('On travaille à un ouvrage au %s.', $lieu),
            self::FabricationAchevee => \sprintf('Un ouvrage s\'achève au %s.', $lieu),
            self::ConvoiParti => \sprintf('Un convoi part%s.', isset($details['partenaire']) ? ' pour '.$details['partenaire'] : ''),
            self::ConvoiRentre => \sprintf('Un convoi rentre%s.', isset($details['partenaire']) ? ' de '.$details['partenaire'] : ''),
            self::ExpeditionEnRoute => 'Une expédition est en route.',
            self::ExpeditionRentree => 'Une expédition rentre.',
            self::EpidemieEclate => \sprintf('La fièvre frappe la ville%s.', null !== $quantite ? ' : '.$quantite.' malades' : ''),
            self::EpidemieFinie => 'La fièvre recule.',
            self::ChefParti => \sprintf('%s quitte son poste et emmène les siens.', $details['libelle'] ?? 'Un chef'),
            self::RivalArrive => \sprintf('%s s\'installe sur votre route.', $details['libelle'] ?? 'Un marchand rival'),
            self::RivalParti => \sprintf('%s se lasse et s\'en va.', $details['libelle'] ?? 'Le marchand rival'),
            self::MedjayBlesse => \sprintf('%s est blessé.', $details['libelle'] ?? 'Un Medjaÿ'),
            self::Fete => \sprintf('Jour de fête : %s.', $details['libelle'] ?? 'une fête du calendrier'),
        };
    }
}
