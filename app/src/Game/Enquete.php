<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les enquêtes (doc 10).
 *
 * Une enquête n'est pas une énigme : elle **se construit**. On ramasse des
 * indices au fil de l'exploration et des rencontres, on les recoupe, et l'on
 * conclut — parfois de travers.
 *
 * **Principale ou secondaire, et la différence est structurante** (décision de
 * la joueuse) : une enquête **principale** porte le fil rouge d'une mission,
 * donc elle se rejoue jusqu'à être résolue — son échec définitif bloquerait la
 * campagne. Une enquête **secondaire** peut se perdre pour de bon, et c'est ce
 * qui donne du poids à une déduction : sans ce risque, conclure au hasard puis
 * recommencer serait toujours la meilleure stratégie.
 *
 * **Trois à cinq indices, dont certains optionnels ou trompeurs** (doc 10).
 * C'est ce qui distingue une enquête d'une case à cocher : si tous les indices
 * concouraient, il suffirait de les compter.
 */
enum Enquete: string
{
    case PassageCoupe = 'passage_coupe';
    case CarrieresAbandonnees = 'carrieres_abandonnees';
    case RumeurDeLaCaravane = 'rumeur_de_la_caravane';
    case MalversationDuRival = 'malversation_du_rival';

    public function libelle(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Le passage coupé',
            self::CarrieresAbandonnees => 'Les carrières abandonnées',
            self::RumeurDeLaCaravane => 'La rumeur de la caravane',
            self::MalversationDuRival => 'La malversation du rival',
        };
    }

    public function question(): string
    {
        return match ($this) {
            self::PassageCoupe => 'Une terre fertile, à deux pas de la ville, que personne ne cultive. Pourquoi la laisse-t-on ?',
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
        return self::PassageCoupe === $this;
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
     * Combien d'indices concordants il faut avoir réunis pour pouvoir
     * conclure. Les fausses pistes n'y comptent pas — c'est précisément ce
     * qu'on doit démêler.
     */
    public function indicesRequis(): int
    {
        return match ($this) {
            self::PassageCoupe => 3,
            self::CarrieresAbandonnees => 2,
            self::RumeurDeLaCaravane => 2,
            self::MalversationDuRival => 2,
        };
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
            self::CarrieresAbandonnees => 'Le front de taille butait sur du calcaire stérile. On abandonne un chantier épuisé comme on quitte une maison vide — sans emporter ce qui ne servira plus ailleurs.',
            self::RumeurDeLaCaravane => 'Le registre du péage ne portait aucun passage : le premier caravanier n\'avait pas pris la piste dont il vantait la sûreté. Le second, lui, avait vu le ouadi de ses yeux.',
            self::MalversationDuRival => 'Ses deux jeux de poids ne s\'accordaient pas, et ses tablettes déclaraient au péage moins qu\'il ne chargeait. Porté au scribe du nome, cela suffit : le marchand a quitté la route sans demander son reste.',
        };
    }

    /**
     * Ce que rapporte une enquête résolue. Le doc 10 veut une « récompense
     * notable » — quatre fois une énigme courte, et un point de renommée : on
     * parle d'une famille qui a démêlé une affaire.
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
