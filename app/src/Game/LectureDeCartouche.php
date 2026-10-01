<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Lire un cartouche royal signe par signe (doc 10).
 *
 * C'est l'exercice qui manquait entre les deux tables : la clé de lecture
 * apprend des **mots**, l'alphabet des **sons**, et un cartouche — la vraie
 * écriture — mêle les deux. Le joueur reçoit un nom de trône réel, ses signes
 * dans l'ordre où ils sont gravés, et doit dire pour chacun ce qu'il y fait.
 * La correction dit alors, signe par signe, **si c'était un son ou un mot**,
 * puis rend le nom entier : sa lecture, sa translittération, son sens.
 *
 * **Seuls les cartouches dont chaque signe est connu sont proposés**
 * (`SigneDeCartouche`) : on n'en approxime aucun.
 *
 * **Le tirage se déduit d'une graine**, comme `ExerciceDesSons` : l'écran la
 * renvoie, le serveur recompose le même exercice pour corriger. Rien n'est
 * stocké entre l'affichage et la réponse.
 *
 * **La récompense ne tombe qu'une fois par quinzaine**, et seulement quand
 * tous les signes sont lus juste : l'exercice se refait sans limite.
 *
 * Le disque solaire s'écrit en tête et se lit à la fin (l'antéposition
 * honorifique, voir `CartoucheRoyal::signes()`) : l'exercice demande ce que fait
 * chaque signe, pas dans quel ordre on le prononce.
 */
final readonly class LectureDeCartouche
{
    public const int RECOMPENSE_EN_DEBEN = 10;

    /**
     * Combien de propositions de trop on glisse parmi les bonnes, pour qu'un
     * signe ne se devine pas par élimination.
     */
    public const int PROPOSITIONS_DE_TROP = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Les cartouches dont **tous** les signes sont connus.
     *
     * @return list<CartoucheRoyal>
     */
    public static function eligibles(): array
    {
        return array_values(array_filter(
            CartoucheRoyal::cases(),
            static fn (CartoucheRoyal $cartouche): bool => [] === array_filter(
                $cartouche->codesDeGardiner(),
                static fn (string $code): bool => null === SigneDeCartouche::tryFrom($code),
            ),
        ));
    }

    /**
     * L'exercice que cette graine donne.
     *
     * `signes` : un par code distinct, dans l'ordre gravé, avec son dessin.
     * `options` : les lectures possibles, les justes mêlées à quelques autres.
     *
     * @return array{
     *     cartouche: CartoucheRoyal,
     *     signes: list<array{code: string, glyphe: string}>,
     *     options: list<array{valeur: string, libelle: string}>
     * }
     */
    public static function exercice(int $graine): array
    {
        $hasard = new Randomizer(new Mt19937($graine));
        $eligibles = self::eligibles();
        $cartouche = $eligibles[$hasard->getInt(0, \count($eligibles) - 1)];

        $glyphes = preg_split('//u', $cartouche->signes(), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $signes = [];

        foreach ($cartouche->codesDeGardiner() as $rang => $code) {
            if (isset($signes[$code])) {
                continue;
            }

            $signes[$code] = ['code' => $code, 'glyphe' => $glyphes[$rang] ?? ''];
        }

        $justes = array_map(static fn (string $code): SigneDeCartouche => SigneDeCartouche::from($code), array_keys($signes));
        $lectures = array_map(static fn (SigneDeCartouche $s): string => $s->translitteration(), $justes);

        // Les propositions de trop ne doivent pas se lire comme une bonne.
        $autres = array_values(array_filter(
            SigneDeCartouche::cases(),
            static fn (SigneDeCartouche $s): bool => !\in_array($s->translitteration(), $lectures, true),
        ));
        $autres = $hasard->shuffleArray($autres);

        $propositions = [];

        foreach ([...$justes, ...\array_slice($autres, 0, self::PROPOSITIONS_DE_TROP)] as $signe) {
            $propositions[$signe->translitteration()] ??= $signe;
        }

        return [
            'cartouche' => $cartouche,
            'signes' => array_values($signes),
            'options' => array_values(array_map(
                static fn (SigneDeCartouche $s): array => ['valeur' => $s->value, 'libelle' => $s->libelle()],
                $hasard->shuffleArray(array_values($propositions)),
            )),
        ];
    }

    /**
     * Corrige la lecture.
     *
     * @param array<string, mixed> $reponses le code choisi, par code de signe gravé
     *
     * @return array{juste: bool, bonnes: int, total: int, recompense: int, lecon: string, details: list<string>}
     */
    public function repondre(GameSave $partie, int $graine, array $reponses): array
    {
        $exercice = self::exercice($graine);
        $cartouche = $exercice['cartouche'];
        $bonnes = 0;
        $details = [];

        foreach ($exercice['signes'] as $signe) {
            $attendu = SigneDeCartouche::from($signe['code']);
            $donne = \is_string($reponses[$signe['code']] ?? null) ? SigneDeCartouche::tryFrom($reponses[$signe['code']]) : null;

            if (null !== $donne && $donne->seLitCommeLUnAutre($attendu)) {
                ++$bonnes;
            }

            $details[] = \sprintf(
                '%s se lit « %s » (%s) : il %s.',
                $signe['glyphe'],
                $attendu->translitteration(),
                $attendu->sens(),
                $attendu->libelleDeNature(),
            );
        }

        $total = \count($exercice['signes']);
        $juste = $bonnes === $total;
        $recompense = 0;
        $ville = $partie->getVille();

        if ($juste && !$ville->lectureDeCartoucheRecompenseAuCycle($partie->getCycle())) {
            $ville->marquerLectureDeCartoucheRecompensee($partie->getCycle());
            $ville->crediterRessources([Ressource::Deben->value => self::RECOMPENSE_EN_DEBEN]);
            $recompense = self::RECOMPENSE_EN_DEBEN;
        }

        $this->entityManager->flush();

        return [
            'juste' => $juste,
            'bonnes' => $bonnes,
            'total' => $total,
            'recompense' => $recompense,
            'lecon' => \sprintf(
                '%s se lit %s (%s) : « %s ». Le disque solaire est écrit en tête par déférence, '
                .'mais il se prononce à la fin.',
                $cartouche->signes(),
                $cartouche->lecture(),
                $cartouche->translitteration(),
                $cartouche->sens(),
            ),
            'details' => $details,
        ];
    }
}
