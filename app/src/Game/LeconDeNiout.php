<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La leçon fondatrice : écrire **Niout**, le nom du jeu (doc 10).
 *
 * « Apprendre à écrire Niout (niwt, "la ville") avec les signes n + i + w + t,
 * geste fondateur qui ancre l'apprentissage dès la première quinzaine. »
 *
 * **Ce n'est pas une inscription du fil rouge.** `FilRouge::acte()` se déduit
 * des inscriptions lues ; y greffer l'alphabet mêlerait les deux pistes. La
 * leçon vit à côté, et ne décide rien de la mission.
 *
 * **Elle se retente**, contrairement aux énigmes à choix multiple. Le motif de
 * la règle « on ne répond qu'une fois » est qu'avec quatre propositions on
 * essaie tout ; remettre quatre signes dans l'ordre a vingt-quatre arrangements
 * possibles, et c'est un **exercice**, pas une devinette — on apprend en
 * recommençant. La récompense, elle, ne tombe qu'une fois : sinon l'exercice
 * deviendrait une rente.
 */
final readonly class LeconDeNiout
{
    /**
     * Les quatre signes, dans l'ordre où ils s'écrivent. L'égyptien ne notait
     * pas les voyelles : *niwt* se lit « Niout » par convention, et le mot
     * s'écrit n · i · w · t.
     */
    public const array SIGNES = [
        SigneAlphabetique::FiletDEau,
        SigneAlphabetique::RoseauFleuri,
        SigneAlphabetique::PoussinDeCaille,
        SigneAlphabetique::Pain,
    ];

    public const string MOT = 'Niout';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Le mot écrit, glyphe à glyphe.
     */
    public static function motEcrit(): string
    {
        return implode('', array_map(static fn (SigneAlphabetique $s): string => $s->signe(), self::SIGNES));
    }

    /**
     * Ce que la leçon enseigne, dit dans les deux cas — juste ou faux. C'est le
     * gain de l'exercice : ce qu'on a compris reste, la poignée de deben passe.
     */
    public static function explication(): string
    {
        return 'L\'égyptien ne notait pas les voyelles : on écrit n · i · w · t, et l\'on convient '
            .'de lire « Niout ». Le mot veut dire « la ville » — c\'est le nom de ce jeu, et '
            .'c\'est ce que le pharaon vous demande de faire vivre.';
    }

    /**
     * Répond à la leçon. La proposition est la liste ordonnée des valeurs de
     * signe, telle que l'écran l'a composée.
     *
     * @param list<string> $ordre
     *
     * @return array{juste: bool, explication: string, recompense: int}
     */
    public function repondre(GameSave $partie, array $ordre): array
    {
        $ville = $partie->getVille();
        $attendu = array_map(static fn (SigneAlphabetique $s): string => $s->value, self::SIGNES);

        $juste = $ordre === $attendu;
        $recompense = 0;

        if ($juste && !$ville->aEcritNiout()) {
            // Une seule fois : l'exercice se refait autant qu'on veut, il ne se
            // monnaie pas deux fois.
            $ville->marquerNioutEcrite();
            $ville->crediterRessources([Ressource::Deben->value => Enigme::RECOMPENSE_EN_DEBEN]);
            $recompense = Enigme::RECOMPENSE_EN_DEBEN;
        }

        $this->entityManager->flush();

        return [
            'juste' => $juste,
            'explication' => self::explication(),
            'recompense' => $recompense,
        ];
    }
}
