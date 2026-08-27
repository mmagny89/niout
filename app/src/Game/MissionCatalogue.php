<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les dix missions de la campagne, dans leur ordre imposé (doc 09, doc 11).
 *
 * L'ordre suit la difficulté croissante, qui s'aligne naturellement sur la
 * chronologie des règnes : l'expansion égyptienne s'est faite par cercles de
 * plus en plus larges autour de la vallée du Nil.
 */
final class MissionCatalogue
{
    /**
     * @var array<int, Mission>|null
     */
    private static ?array $missions = null;

    public function get(int $numero): Mission
    {
        $missions = self::missions();

        return $missions[$numero]
            ?? throw new \InvalidArgumentException(\sprintf('Aucune mission numéro %d.', $numero));
    }

    /**
     * @return array<int, Mission>
     */
    public function toutes(): array
    {
        return self::missions();
    }

    /**
     * @return array<int, Mission>
     */
    private static function missions(): array
    {
        return self::$missions ??= self::construire();
    }

    /**
     * @return array<int, Mission>
     */
    private static function construire(): array
    {
        $missions = [
            new Mission(
                1, 'Delta du Nord', 'Avaris', 'Ahmôsis Ier', TypeDeMission::Developper, 0,
                'Ancienne capitale des Hyksôs, reprise par Ahmôsis Ier. La ville sort d\'un long conflit : il faut la repeupler et rouvrir son commerce.',
            ),
            new Mission(
                2, 'Haute-Nubie', 'Saï', 'Thoutmôsis Ier', TypeDeMission::Fonder, 1,
                'Après la prise de Kerma, Thoutmôsis Ier étend le contrôle égyptien vers le sud. L\'île de Saï doit devenir une implantation durable.',
            ),
            new Mission(
                3, 'Mer Rouge', 'Mersa Gaouasis', 'Hatchepsout', TypeDeMission::Developper, 2,
                'Le port d\'où partent les navires vers Pount. Hatchepsout veut y relancer les expéditions qui rapportent encens et myrrhe.',
            ),
            new Mission(
                4, 'Levant', 'Megiddo', 'Thoutmôsis III', TypeDeMission::Securiser, 3,
                'Megiddo vient de tomber après la bataille. Thoutmôsis III y installe une garnison : la ville est égyptienne, mais la région reste incertaine.',
            ),
            new Mission(
                5, 'Thèbes et la Vallée', 'Malkata', 'Amenhotep III', TypeDeMission::Fonder, 4,
                'Amenhotep III fait bâtir une cité royale sur la rive ouest, avec son port artificiel. Un chantier de prestige, sous le regard de Thèbes.',
            ),
            new Mission(
                6, 'Moyenne-Égypte', 'Akhetaton', 'Akhenaton', TypeDeMission::Fonder, 5,
                'Une capitale entièrement neuve, sortie du désert sur ordre d\'Akhenaton. Rien n\'existe encore sur ce site.',
            ),
            new Mission(
                7, 'Basse-Nubie', 'Éléphantine', 'Séthi Ier', TypeDeMission::Developper, 6,
                'Ville-frontière et poste douanier de la première cataracte, où les scribes contrôlent l\'or et l\'ivoire venus du sud.',
            ),
            new Mission(
                8, 'Fayoum', 'Shedet', 'Ramsès III', TypeDeMission::Developper, 7,
                'Capitale du Fayoum et siège du culte de Sobek. Le règne est prospère en apparence, mais traversé de tensions et de menaces extérieures.',
            ),
            new Mission(
                9, 'Désert oriental', 'Ouadi Hammamat', 'Ramsès IV', TypeDeMission::Developper, 8,
                'Un camp minier dressé en plein désert, sans agriculture ni fleuve. Ramsès IV y engage la plus vaste expédition de pierre du Nouvel Empire.',
            ),
            new Mission(
                10, 'Sinaï', 'Serabit el-Khadim', 'Ramsès IV', TypeDeMission::Developper, 9,
                'Les mines de turquoise et de cuivre, gardées par le temple d\'Hathor, Dame de la turquoise. Tout, jusqu\'aux vivres, doit y être acheminé.',
            ),
        ];

        $indexees = [];
        foreach ($missions as $mission) {
            $indexees[$mission->numero] = $mission;
        }

        return $indexees;
    }
}
