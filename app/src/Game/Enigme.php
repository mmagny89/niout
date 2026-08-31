<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Les énigmes courtes du doc 10 : devinettes, oracles, associations
 * symboliques et reconnaissance astronomique.
 *
 * Toutes à choix multiple, toutes **facultatives** (décision de la joueuse) :
 * une mission en porte, aucune n'est requise pour la finir. Elles récompensent
 * la curiosité, elles ne bloquent jamais.
 *
 * **Une seule tentative**, en revanche — et c'est ce qui les rend
 * intéressantes. Avec trois réponses proposées et un droit de reprise
 * illimité, on essaie tout et la récompense est acquise : il n'y aurait plus
 * de question, seulement un formulaire. C'est aussi ce que la joueuse a
 * tranché pour les enquêtes secondaires, qui peuvent échouer définitivement.
 *
 * **Le contenu est vrai, et dit d'où il vient.** L'iconographie et
 * l'astronomie égyptiennes sont réelles ; les devinettes sont pour moitié
 * inspirées de traditions antiques attestées, pour moitié originales écrites
 * dans le même esprit — le doc 10 le prévoit ainsi, et `sourceAttestee()` le
 * dit au joueur plutôt que de tout présenter comme antique.
 */
enum Enigme: string
{
    // Association symbolique — iconographie réelle.
    case IbisDeThot = 'ibis_de_thot';
    case ChacalDAnubis = 'chacal_anubis';
    case FauconDHorus = 'faucon_horus';
    case ScarabeeDeKhepri = 'scarabee_khepri';

    // Reconnaissance astronomique — le ciel des Égyptiens.
    case EtoileDeLaCrue = 'etoile_de_la_crue';
    case ConstellationDOsiris = 'constellation_osiris';
    case LesDecans = 'les_decans';

    // Devinettes et oracles.
    case OracleDeKarnak = 'oracle_de_karnak';
    case DevinetteDuFleuve = 'devinette_du_fleuve';
    case DevinetteDuScribe = 'devinette_du_scribe';
    case DevinetteDeLaBriqueCrue = 'devinette_de_la_brique_crue';

    public const int RECOMPENSE_EN_DEBEN = 15;

    /**
     * Où l'on rencontre l'énigme — et donc quel bâtiment il faut avoir dressé.
     * C'est ce qui donne à l'Auberge sa première raison d'exister.
     */
    public function lieu(): TypeDeBatiment
    {
        return match ($this) {
            self::IbisDeThot, self::ChacalDAnubis, self::FauconDHorus, self::ScarabeeDeKhepri,
            self::EtoileDeLaCrue, self::ConstellationDOsiris, self::LesDecans => TypeDeBatiment::MaisonDesScribes,
            self::OracleDeKarnak => TypeDeBatiment::Temple,
            self::DevinetteDuFleuve, self::DevinetteDuScribe,
            self::DevinetteDeLaBriqueCrue => TypeDeBatiment::Auberge,
        };
    }

    public function enonce(): string
    {
        return match ($this) {
            self::IbisDeThot => 'Un ibis se tient gravé au-dessus d\'une palette de scribe. Quel dieu ce bec recourbé désigne-t-il ?',
            self::ChacalDAnubis => 'Un chacal noir couché sur un coffre garde l\'entrée d\'un tombeau. Qui veille ainsi ?',
            self::FauconDHorus => 'Un faucon coiffé de la double couronne domine la façade d\'un palais. De qui s\'agit-il ?',
            self::ScarabeeDeKhepri => 'Un scarabée pousse devant lui un disque. Quelle idée les Égyptiens y lisaient-ils ?',
            self::EtoileDeLaCrue => 'Une étoile reparaît à l\'aube après soixante-dix jours d\'absence, et l\'eau monte peu après. Laquelle ?',
            self::ConstellationDOsiris => 'Trois étoiles alignées dominent le ciel d\'hiver. Les Égyptiens y voyaient Sah. Quel dieu Sah devient-il ?',
            self::LesDecans => 'Les scribes du ciel découpaient l\'année en trente-six groupes d\'étoiles, chacun se levant à son tour. Comment les appelle-t-on ?',
            self::OracleDeKarnak => 'À Karnak, on portait la barque du dieu en procession pour trancher un procès. Comment le dieu répondait-il ?',
            self::DevinetteDuFleuve => 'Il part chaque année, revient chaque année, et l\'on ne lui connaît ni jambes ni bateau. Qui est-ce ?',
            self::DevinetteDuScribe => 'Il n\'a pas de bouche et il parle ; il n\'a pas d\'oreilles et il retient. Qu\'est-ce ?',
            self::DevinetteDeLaBriqueCrue => 'On la fait avec le fleuve et avec le soleil, et toute l\'Égypte y habite. Qu\'est-ce ?',
        };
    }

    /**
     * Les réponses proposées. **La bonne est toujours la première ici** ; c'est
     * au rendu qu'elles sont mélangées, comme les jetons du déchiffrage —
     * sinon la réponse se lit dans la source de la page.
     *
     * @return list<string>
     */
    public function propositions(): array
    {
        return match ($this) {
            self::IbisDeThot => ['Thot', 'Horus', 'Ptah', 'Sobek'],
            self::ChacalDAnubis => ['Anubis', 'Seth', 'Amon-Rê', 'Hâpi'],
            self::FauconDHorus => ['Horus', 'Thot', 'Osiris', 'Khnoum'],
            self::ScarabeeDeKhepri => ['Le soleil qui renaît chaque matin', 'La mort et l\'embaumement', 'La crue du fleuve', 'La victoire au combat'],
            self::EtoileDeLaCrue => ['Sopdet, que les Grecs nomment Sirius', 'L\'étoile polaire', 'Vénus au matin', 'Aldébaran'],
            self::ConstellationDOsiris => ['Osiris', 'Rê', 'Anubis', 'Sekhmet'],
            self::LesDecans => ['Les décans', 'Les heures', 'Les épagomènes', 'Les nomes'],
            self::OracleDeKarnak => ['Par le mouvement de la barque, que les porteurs sentaient avancer ou reculer', 'Par un songe envoyé au plaignant', 'Par le tirage d\'un jeton dans une urne', 'Par la parole du pharaon en personne'],
            self::DevinetteDuFleuve => ['La crue', 'Le vent du nord', 'La lune', 'La caravane'],
            self::DevinetteDuScribe => ['L\'écriture', 'Le rêve', 'La statue du dieu', 'L\'écho de la falaise'],
            self::DevinetteDeLaBriqueCrue => ['La brique crue', 'Le pain', 'La jarre', 'Le papyrus'],
        };
    }

    public function bonneReponse(): string
    {
        return $this->propositions()[0];
    }

    /**
     * Ce qu'on apprend en répondant — juste ou faux. **C'est le vrai gain de
     * l'énigme** : la récompense en deben passe, la connaissance reste, et
     * l'on ne perd rien à s'être trompé.
     */
    public function explication(): string
    {
        return match ($this) {
            self::IbisDeThot => 'Thot, dieu de l\'écriture et du calcul, se montre en ibis ou en babouin. C\'est lui que les scribes invoquent avant de tailler leur calame.',
            self::ChacalDAnubis => 'Anubis, à tête de chacal, veille sur l\'embaumement et sur les nécropoles — que les chacals rôdaient réellement.',
            self::FauconDHorus => 'Horus, le faucon, est le dieu de la royauté : le pharaon vivant est Horus, et le pharaon mort devient Osiris.',
            self::ScarabeeDeKhepri => 'Khépri, le scarabée, pousse le soleil au-dessus de l\'horizon comme le bousier sa boule : c\'est le soleil du matin, celui qui devient.',
            self::EtoileDeLaCrue => 'Le lever héliaque de Sopdet — Sirius — précédait de peu la crue et ouvrait l\'année égyptienne. Le calendrier tout entier s\'y accrochait.',
            self::ConstellationDOsiris => 'Sah, notre Orion, est l\'image d\'Osiris au ciel ; Sopdet, sa compagne, est Isis. Le couple traverse le ciel d\'hiver.',
            self::LesDecans => 'Trente-six décans, un par groupe de dix jours : le ciel servait d\'horloge, et les cercueils en portaient la table pour que le mort lise l\'heure.',
            self::OracleDeKarnak => 'La barque portée sur les épaules des prêtres avançait ou reculait à la question posée. Des procès entiers se sont tranchés ainsi, et les archives en gardent la trace.',
            self::DevinetteDuFleuve => 'La crue, qui part et revient chaque année. Toute l\'Égypte se règle sur ce départ et ce retour.',
            self::DevinetteDuScribe => 'L\'écriture : elle parle sans bouche et retient sans oreilles. Les scribes en tiraient une fierté qu\'ils ne cachaient guère.',
            self::DevinetteDeLaBriqueCrue => 'La brique crue : limon du fleuve, paille et soleil. Les palais eux-mêmes en étaient faits — la pierre était pour les dieux et pour les morts.',
        };
    }

    /**
     * Dit au joueur si le contenu vient d'une source antique ou s'il a été
     * écrit dans son esprit (doc 10). Tout présenter comme antique
     * tromperait ; tout présenter comme inventé effacerait ce qui est vrai.
     */
    public function sourceAttestee(): bool
    {
        return match ($this) {
            self::DevinetteDuFleuve, self::DevinetteDuScribe, self::DevinetteDeLaBriqueCrue => false,
            default => true,
        };
    }

    /**
     * Les énigmes qu'on peut tenter : celles dont le lieu est dressé, et
     * qu'on n'a pas déjà tentées — juste ou faux.
     *
     * @return list<self>
     */
    public static function disponiblesPour(City $ville): array
    {
        $tentees = $ville->enigmesTentees();
        $disponibles = [];

        foreach (self::cases() as $enigme) {
            if (\in_array($enigme, $tentees, true) || !$ville->possede($enigme->lieu())) {
                continue;
            }

            $disponibles[] = $enigme;
        }

        return $disponibles;
    }
}
