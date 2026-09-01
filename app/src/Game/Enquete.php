<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Les enquêtes (doc 10).
 *
 * Une enquête n'est pas une énigme : elle **se construit**. On ramasse des
 * indices au fil de l'exploration et des rencontres, on les recoupe, et l'on
 * conclut — parfois de travers.
 *
 * **Principale ou secondaire, et la différence est structurante** (décision de
 * la joueuse) : une **principale** porte le fil rouge d'une mission, donc elle
 * se rejoue jusqu'à être résolue — son échec définitif bloquerait la campagne.
 * Une **secondaire** peut se perdre pour de bon, et c'est ce qui donne du
 * poids à une déduction.
 *
 * **Chaque mission a la sienne**, et l'on ne ramasse que les indices de
 * celle-là : trouver au Delta une borne déplacée du Sinaï n'aurait aucun sens,
 * et remplirait le dossier d'une enquête qu'on ne mène pas.
 *
 * **Trois à cinq indices, dont certains optionnels ou trompeurs** (doc 10).
 * C'est ce qui distingue une enquête d'une case à cocher : si tous les indices
 * concouraient, il suffirait de les compter.
 */
enum Enquete: string
{
    // Un fil rouge par mission (doc 09).
    case PassageCoupe = 'passage_coupe';
    case BornesDeplacees = 'bornes_deplacees';
    case FlotteQuiNePartPas = 'flotte_qui_ne_part_pas';
    case PorteLaisseeOuverte = 'porte_laissee_ouverte';
    case ChantierQuiNavancePas = 'chantier_qui_navance_pas';
    case EauQuiManque = 'eau_qui_manque';
    case OrQuiSevapore = 'or_qui_sevapore';
    case CanalEnvase = 'canal_envase';
    case HommesQuiDesertent = 'hommes_qui_desertent';
    case GalerieEffondree = 'galerie_effondree';

    // Les secondaires, communes à toutes les régions.
    case CarrieresAbandonnees = 'carrieres_abandonnees';
    case RumeurDeLaCaravane = 'rumeur_de_la_caravane';
    case MalversationDuRival = 'malversation_du_rival';

    /**
     * L'enquête qui porte le fil rouge de cette mission.
     */
    public static function duFilRouge(int $mission): ?self
    {
        return match ($mission) {
            1 => self::PassageCoupe,
            2 => self::BornesDeplacees,
            3 => self::FlotteQuiNePartPas,
            4 => self::PorteLaisseeOuverte,
            5 => self::ChantierQuiNavancePas,
            6 => self::EauQuiManque,
            7 => self::OrQuiSevapore,
            8 => self::CanalEnvase,
            9 => self::HommesQuiDesertent,
            10 => self::GalerieEffondree,
            default => null,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Le passage coupé',
            self::BornesDeplacees => 'Les bornes déplacées',
            self::FlotteQuiNePartPas => 'La flotte qui ne part pas',
            self::PorteLaisseeOuverte => 'La porte laissée ouverte',
            self::ChantierQuiNavancePas => 'Le chantier qui n\'avance pas',
            self::EauQuiManque => 'L\'eau qui manque',
            self::OrQuiSevapore => 'L\'or qui s\'évapore',
            self::CanalEnvase => 'Le canal envasé',
            self::HommesQuiDesertent => 'Les hommes qui désertent',
            self::GalerieEffondree => 'La galerie effondrée',
            self::CarrieresAbandonnees => 'Les carrières abandonnées',
            self::RumeurDeLaCaravane => 'La rumeur de la caravane',
            self::MalversationDuRival => 'La malversation du rival',
        };
    }

    public function question(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Une terre fertile, à deux pas de la ville, que personne ne cultive. Pourquoi la laisse-t-on ?',
            self::BornesDeplacees => 'Les bornes de la terre royale ne sont plus où le cadastre les met. Qui les a bougées, et jusqu\'où ?',
            self::FlotteQuiNePartPas => 'Les coques sont montées, l\'équipage est payé, et rien n\'appareille. Qu\'est-ce qui retient la flotte ?',
            self::PorteLaisseeOuverte => 'La garnison est prévenue de chaque ronde avant qu\'elle ne parte. Qui renseigne l\'extérieur ?',
            self::ChantierQuiNavancePas => 'Le grès arrive, les hommes sont là, et le chantier royal stagne. Où passe l\'ouvrage ?',
            self::EauQuiManque => 'Une capitale doit sortir du sable, mais le puits creusé ne donne rien. Où est l\'eau ?',
            self::OrQuiSevapore => 'Les convois de Nubie arrivent plus légers qu\'ils ne sont partis. Où l\'or s\'en va-t-il ?',
            self::CanalEnvase => 'Le canal qui nourrit le Fayoum n\'apporte plus qu\'un filet. L\'envasement suffit-il à l\'expliquer ?',
            self::HommesQuiDesertent => 'Le camp perd des hommes chaque quinzaine, et pas au combat. Pourquoi partent-ils ?',
            self::GalerieEffondree => 'La galerie de turquoise s\'est effondrée sans qu\'on tremble. Était-ce un accident ?',
            self::CarrieresAbandonnees => 'Des outils rouillés, des cabanes vides : on a extrait ici, puis on est parti. Qu\'a-t-on fui ?',
            self::RumeurDeLaCaravane => 'Deux caravaniers racontent la même route, et ne s\'accordent sur rien. Lequel ment ?',
            self::MalversationDuRival => 'Un marchand s\'est installé sur votre route et vous prend une part de ce qui passe. Sur quoi tient-il ?',
        };
    }

    /**
     * Une principale porte le fil rouge d'une mission ; elle se rejoue jusqu'à
     * être résolue. Une secondaire peut se perdre.
     */
    public function estPrincipale(): bool
    {
        return match ($this) {
            self::CarrieresAbandonnees, self::RumeurDeLaCaravane, self::MalversationDuRival => false,
            default => true,
        };
    }

    /**
     * Une enquête qui n'a de sens qu'avec un rival en face : ses indices ne se
     * ramassent pas tant que personne ne vous concurrence. Sans cette réserve,
     * on démonterait un marchand avant qu'il n'arrive.
     */
    public function viseUnRival(): bool
    {
        return self::MalversationDuRival === $this;
    }

    /**
     * Ce qu'on peut enquêter dans cette partie : le fil rouge de sa mission,
     * les deux secondaires communes, et celle du rival s'il est là.
     */
    public function seMeneDans(GameSave $partie): bool
    {
        if ($this->viseUnRival()) {
            return null !== $partie->getVille()->getRival();
        }

        if (!$this->estPrincipale()) {
            return true;
        }

        return $this === self::duFilRouge($partie->getMission() ?? 0);
    }

    /**
     * Combien d'indices concordants il faut avoir réunis pour pouvoir
     * conclure. Les fausses pistes n'y comptent pas — c'est précisément ce
     * qu'on doit démêler.
     */
    public function indicesRequis(): int
    {
        return $this->estPrincipale() ? 3 : 2;
    }

    /**
     * Les conclusions proposées. **La bonne est la première ici** ; elles sont
     * mélangées au rendu, comme les propositions d'une énigme.
     *
     * @return list<string>
     */
    public function conclusions(): array
    {
        return match ($this) {
            self::PassageCoupe => [
                'Des hommes campent sur la route et ont rompu la digue : la terre est coupée, pas stérile.',
                'La terre s\'est épuisée, et plus rien n\'y pousse.',
                'La garnison a interdit le passage sur ordre du nomarque.',
                'Le puits voisin s\'est tari, et les paysans sont partis avec l\'eau.',
            ],
            self::BornesDeplacees => [
                'Un notable local a repoussé les bornes vers le fleuve, et fait recopier le cadastre à sa main.',
                'La crue les a déplacées : personne n\'y est pour rien.',
                'Les Nubiens contestent la frontière et l\'ont reculée de nuit.',
                'Le premier arpenteur s\'est trompé, et l\'erreur se recopie depuis.',
            ],
            self::FlotteQuiNePartPas => [
                'Le bois de charpente livré est vert : il travaillerait en mer, et le maître charpentier refuse d\'appareiller.',
                'Les vents contraires n\'ont pas tourné depuis deux saisons.',
                'L\'équipage réclame une solde arriérée et refuse d\'embarquer.',
                'Le pilote qui connaissait la route de Pount est mort, et nul ne le remplace.',
            ],
            self::PorteLaisseeOuverte => [
                'Un scribe de la garnison vend l\'ordre des rondes, et son train de vie le trahit.',
                'Les rondes suivent le même parcours depuis un an : n\'importe qui les devine.',
                'Un officier cananéen resté en poste renseigne les siens.',
                'Il n\'y a pas de fuite : la ville est simplement trop grande pour la garnison.',
            ],
            self::ChantierQuiNavancePas => [
                'Le grès part la nuit vers un chantier privé, et le contremaître compte deux fois les mêmes blocs.',
                'La pierre livrée se délite : elle vient d\'un mauvais front de taille.',
                'Les hommes sont retenus à la corvée du canal, et ne viennent qu\'à moitié.',
                'Le plan a changé trois fois, et l\'on défait ce qu\'on a bâti.',
            ],
            self::EauQuiManque => [
                'La nappe est plus profonde qu\'ici : c\'est vers la falaise qu\'il faut creuser, comme le montrent les puits anciens.',
                'Le site est sans eau, il faudra tout amener du fleuve.',
                'Le puits est bon mais quelqu\'un le comble la nuit.',
                'La crue de cette année a été trop faible pour recharger la nappe.',
            ],
            self::OrQuiSevapore => [
                'Le poids du péage est faussé, et l\'écart se retrouve chez le peseur.',
                'Les convois sont détroussés au passage de la cataracte.',
                'Le minerai nubien est moins riche qu\'annoncé au départ.',
                'Un scribe recopie mal les quantités, sans malice.',
            ],
            self::CanalEnvase => [
                'On a ouvert une prise d\'eau en amont, sans autorisation, et le canal n\'a plus sa part.',
                'L\'envasement seul l\'explique : il faut curer, voilà tout.',
                'Les crocodiles du lac ont fait fuir les cureurs.',
                'Le canal a changé de lit après la dernière crue.',
            ],
            self::HommesQuiDesertent => [
                'L\'eau des citernes est saumâtre, et les rations arrivent gâtées : ils partent pour ne pas mourir.',
                'Les hommes fuient devant les bandits du ouadi.',
                'On les débauche pour un autre chantier royal, mieux payé.',
                'La corvée est arrivée à son terme et ils rentrent chez eux, tout simplement.',
            ],
            self::GalerieEffondree => [
                'On a suivi le filon en laissant trop peu de piliers : la galerie était condamnée avant de tomber.',
                'Un séisme l\'a fait tomber : le Sinaï en connaît.',
                'Un rival a fait saboter le chantier pour garder le marché.',
                'La déesse a retiré sa protection, faute d\'offrandes.',
            ],
            self::CarrieresAbandonnees => [
                'Le filon était fini : on a laissé les outils sur place plutôt que de les porter.',
                'Une crue exceptionnelle a noyé le chantier et tué les carriers.',
                'Les carriers ont été réquisitionnés pour un chantier royal.',
                'Une malédiction a fait fuir les hommes, qui n\'ont rien osé emporter.',
            ],
            self::RumeurDeLaCaravane => [
                'Le premier ment : il n\'a pas pris la piste, et le registre du péage le confond.',
                'Le second ment : il décrit un ouadi qu\'il n\'a jamais vu.',
                'Tous deux disent vrai : la piste a changé entre leurs passages.',
                'Tous deux mentent : aucune caravane n\'est passée depuis un an.',
            ],
            self::MalversationDuRival => [
                'Il fausse ses poids et sous-déclare au péage : la preuve est dans ses propres tablettes.',
                'Il paie la garnison pour détourner les caravanes, et rien ne l\'écrit.',
                'Il a de meilleurs prix parce qu\'il produit moins cher : rien à lui reprocher.',
                'Il agit pour le compte d\'un nomarque, et l\'on ne touche pas à cela.',
            ],
        };
    }

    public function bonneConclusion(): string
    {
        return $this->conclusions()[0];
    }

    /**
     * Ce qu'on apprend une fois l'enquête close — juste ou non. Comme pour une
     * énigme, **le vrai gain est là** : savoir ce qui s'est passé.
     */
    public function denouement(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Un campement s\'était installé au coude de la route, et la digue rompue noyait les abords pour tenir les curieux à distance. La terre n\'avait rien perdu de sa qualité : il fallait rouvrir le passage, pas renoncer au champ.',
            self::BornesDeplacees => 'Les bornes avaient marché de trois cents coudées vers le fleuve, et le cadastre les suivait — recopié de la main du notable qui y gagnait. Déplacer une borne était un crime que les Égyptiens jugeaient jusque dans l\'au-delà.',
            self::FlotteQuiNePartPas => 'Le bois livré n\'avait pas séché : monté vert, il aurait travaillé en pleine mer Rouge. Le maître charpentier avait raison de retenir la flotte — on ne discute pas avec un homme qui connaît son bois.',
            self::PorteLaisseeOuverte => 'Le scribe de la garnison vendait l\'ordre des rondes, et son train de vie ne tenait pas avec sa solde. Une place forte ne tombe pas par ses murs.',
            self::ChantierQuiNavancePas => 'Le grès partait de nuit vers un chantier qui n\'était pas celui du roi, et le contremaître comptait deux fois les mêmes blocs pour que le registre tombe juste. Les scribes égyptiens tenaient des comptes précis : c\'est ce qui l\'a perdu.',
            self::EauQuiManque => 'La nappe existait, mais plus bas et plus loin — vers la falaise, là où les puits anciens la trouvaient déjà. Fonder une ville sur du sable demande de savoir où l\'eau se cache.',
            self::OrQuiSevapore => 'Le poids du péage était faussé, et l\'écart tenait dans la maison du peseur. L\'or n\'avait jamais quitté Éléphantine : il n\'y était simplement jamais entré dans les comptes.',
            self::CanalEnvase => 'Quelqu\'un avait ouvert une prise d\'eau en amont, et le Bahr Yousef n\'arrivait plus qu\'affaibli. L\'envasement n\'était que ce qu\'on voulait vous faire voir.',
            self::HommesQuiDesertent => 'L\'eau des citernes tournait, les rations arrivaient gâtées, et les hommes partaient pour ne pas y rester. Ramsès IV envoyait des milliers d\'hommes au Ouadi Hammamat : les ravitailler était la moitié de l\'expédition.',
            self::GalerieEffondree => 'On avait suivi le filon en laissant trop peu de piliers. La galerie était condamnée bien avant de tomber, et personne n\'avait voulu le dire au chef de chantier.',
            self::CarrieresAbandonnees => 'Le front de taille butait sur du calcaire stérile. On abandonne un chantier épuisé comme on quitte une maison vide — sans emporter ce qui ne servira plus ailleurs.',
            self::RumeurDeLaCaravane => 'Le registre du péage ne portait aucun passage : le premier caravanier n\'avait pas pris la piste dont il vantait la sûreté. Le second, lui, avait vu le ouadi de ses yeux.',
            self::MalversationDuRival => 'Ses deux jeux de poids ne s\'accordaient pas, et ses tablettes déclaraient au péage moins qu\'il ne chargeait. Porté au scribe du nome, cela suffit : le marchand a quitté la route sans demander son reste.',
        };
    }

    /**
     * Ce que rapporte une enquête résolue. Le doc 10 veut une « récompense
     * notable » — plusieurs fois une énigme courte.
     */
    public function recompenseEnDeben(): int
    {
        return match (true) {
            $this->estPrincipale() => 80,
            // Démonter un rival est la plus longue des trois issues du
            // doc 08 : c'est aussi la plus payante.
            $this->viseUnRival() => 100,
            default => 60,
        };
    }

    /**
     * @return list<Indice>
     */
    public function indices(): array
    {
        $siens = [];

        foreach (Indice::cases() as $indice) {
            if ($indice->enquete() === $this) {
                $siens[] = $indice;
            }
        }

        return $siens;
    }
}
