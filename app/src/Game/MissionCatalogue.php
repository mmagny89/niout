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
        // Le **bois local** (doc 08) est porté par les six régions que borde le
        // Nil. Le Fayoum en fait partie sans que le fleuve y soit modélisé —
        // le Bahr Yousef, son canal, n'est pas une colonne de Nil ici : le bois
        // y pousse donc sur la terre fertile seule, faute de terre
        // broussailleuse, qui ne se sème que dans les régions à Nil.

        $missions = [
            new Mission(
                1, 'Delta du Nord', 'Avaris', 'Ahmôsis Ier', TypeDeMission::Developper, 0,
                'Ancienne capitale des Hyksôs, reprise par Ahmôsis Ier. La ville sort d\'un long conflit : il faut la repeupler et rouvrir son commerce.',
                new GeographieDeRegion(nil: true, mediterranee: true, ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::BoisLocal, Ressource::Calcaire]),
            ),
            new Mission(
                2, 'Haute-Nubie', 'Saï', 'Thoutmôsis Ier', TypeDeMission::Fonder, 1,
                'Après la prise de Kerma, Thoutmôsis Ier étend le contrôle égyptien vers le sud. L\'île de Saï doit devenir une implantation durable.',
                new GeographieDeRegion(nil: true, desert: true, desertDominant: true, ressourcesDeZone: [Ressource::Argile, Ressource::BoisLocal, Ressource::Or, Ressource::Ivoire, Ressource::Ebene]),
            ),
            new Mission(
                3, 'Mer Rouge', 'Mersa Gaouasis', 'Hatchepsout', TypeDeMission::Developper, 2,
                'Le port d\'où partent les navires vers Pount. Hatchepsout veut y relancer les expéditions qui rapportent encens et myrrhe.',
                new GeographieDeRegion(merRouge: true, desert: true, ressourcesDeZone: [Ressource::Encens, Ressource::Myrrhe, Ressource::Sel]),
            ),
            new Mission(
                4, 'Levant', 'Megiddo', 'Thoutmôsis III', TypeDeMission::Securiser, 3,
                'Megiddo vient de tomber après la bataille. Thoutmôsis III y installe une garnison : la ville est égyptienne, mais la région reste incertaine.',
                new GeographieDeRegion(mediterranee: true, foret: true, ressourcesDeZone: [Ressource::BoisDeCedre]),
            ),
            new Mission(
                5, 'Thèbes et la Vallée', 'Malkata', 'Amenhotep III', TypeDeMission::Fonder, 4,
                'Amenhotep III fait bâtir une cité royale sur la rive ouest, avec son port artificiel. Un chantier de prestige, sous le regard de Thèbes.',
                new GeographieDeRegion(nil: true, desert: true, ressourcesDeZone: [Ressource::Argile, Ressource::BoisLocal, Ressource::Gres, Ressource::Calcaire]),
            ),
            new Mission(
                6, 'Moyenne-Égypte', 'Akhetaton', 'Akhenaton', TypeDeMission::Fonder, 5,
                'Une capitale entièrement neuve, sortie du désert sur ordre d\'Akhenaton. Rien n\'existe encore sur ce site.',
                new GeographieDeRegion(nil: true, desert: true, ressourcesDeZone: [Ressource::Calcaire, Ressource::Albatre, Ressource::Argile, Ressource::BoisLocal]),
            ),
            new Mission(
                7, 'Basse-Nubie', 'Éléphantine', 'Séthi Ier', TypeDeMission::Developper, 6,
                'Ville-frontière et poste douanier de la première cataracte, où les scribes contrôlent l\'or et l\'ivoire venus du sud.',
                new GeographieDeRegion(nil: true, desert: true, ressourcesDeZone: [Ressource::Argile, Ressource::BoisLocal, Ressource::Granite]),
            ),
            new Mission(
                8, 'Fayoum', 'Shedet', 'Ramsès III', TypeDeMission::Developper, 7,
                'Capitale du Fayoum et siège du culte de Sobek. Le règne est prospère en apparence, mais traversé de tensions et de menaces extérieures.',
                // **Le Fayoum a de l'eau** : le Bahr Youssef, une branche du
                // Nil, alimente le lac Moeris sur la rive duquel Shedet est
                // bâtie, et la région monte avec la crue. Sans ce `nil`, la
                // mission n'avait aucune route commerciale atteignable — son
                // unique partenaire est fluvial, donc suspendu à un Port, donc
                // à un point d'eau que la carte ne produisait jamais. Et Sobek
                // y était inerte, dans la ville dont il est le dieu.
                new GeographieDeRegion(nil: true, desert: true, oasis: true, ressourcesDeZone: [Ressource::Argile, Ressource::BoisLocal, Ressource::Calcaire, Ressource::Natron]),
            ),
            new Mission(
                9, 'Désert oriental', 'Ouadi Hammamat', 'Ramsès IV', TypeDeMission::Developper, 8,
                'Un camp minier dressé en plein désert, sans agriculture ni fleuve. Ramsès IV y engage la plus vaste expédition de pierre du Nouvel Empire.',
                new GeographieDeRegion(desert: true, desertDominant: true, oasis: true, ressourcesDeZone: [Ressource::Grauwacke, Ressource::Or, Ressource::Cuivre]),
            ),
            new Mission(
                10, 'Sinaï', 'Serabit el-Khadim', 'Ramsès IV', TypeDeMission::Developper, 9,
                'Les mines de turquoise et de cuivre, gardées par le temple d\'Hathor, Dame de la turquoise. Tout, jusqu\'aux vivres, doit y être acheminé.',
                new GeographieDeRegion(merRouge: true, desert: true, desertDominant: true, ressourcesDeZone: [Ressource::Cuivre, Ressource::Turquoise]),
            ),
        ];

        $indexees = [];
        foreach ($missions as $mission) {
            $indexees[$mission->numero] = $mission;
        }

        return $indexees;
    }
}
