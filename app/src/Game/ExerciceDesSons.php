<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pratiquer les sons : une série de questions sur les signes que la ville
 * connaît déjà (doc 10).
 *
 * La Maison des scribes montrait les vingt-quatre signes dans une grille, et
 * l'unique exercice — écrire *Niout* — n'en demandait que quatre. Or on
 * n'apprend pas un signe en le voyant : on l'apprend en le retrouvant. Trois
 * sortes de questions, qui tournent dans le même ordre :
 *
 * - **le signe vers le son** : « quel son note ce dessin ? » ;
 * - **le son vers le signe** : « quel dessin note le *r* ? » — c'est le sens
 *   qui sert à écrire ;
 * - **l'objet vers le son** : « que note la chouette ? » — le mnémonique, le
 *   dessin dit en mots.
 *
 * **La série se déduit d'une graine, elle ne se stocke pas.** L'écran tire la
 * graine, la renvoie avec les réponses, et le serveur recompose les mêmes
 * questions pour corriger : rien n'est conservé entre l'affichage et la
 * réponse. Inventer sa graine n'ouvre rien — on ne choisit pas ce qu'on
 * demande, seulement lequel des tirages on subit, et la récompense est limitée.
 *
 * **Elle ne porte que sur ce que la ville sait.** Interroger sur un signe qu'on
 * n'a pas encore appris serait injuste, et révélerait justement ce que la
 * Maison des scribes doit ouvrir.
 *
 * **La récompense ne tombe qu'une fois par quinzaine**, et seulement à partir
 * de `SEUIL_DE_REUSSITE` bonnes réponses : l'exercice se refait autant qu'on
 * veut, il ne devient pas une rente.
 */
final readonly class ExerciceDesSons
{
    public const int QUESTIONS = 6;
    public const int SEUIL_DE_REUSSITE = 5;
    public const int RECOMPENSE_EN_DEBEN = 10;

    /**
     * Le nombre de propositions par question, quand la ville connaît assez de
     * signes pour les fournir.
     */
    public const int PROPOSITIONS = 4;

    private const string GLYPHE_VERS_SON = 'glyphe_vers_son';
    private const string SON_VERS_GLYPHE = 'son_vers_glyphe';
    private const string OBJET_VERS_SON = 'objet_vers_son';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * La série de questions que cette graine donne à cette ville.
     *
     * @return list<array{
     *     type: string,
     *     enonce: string,
     *     glyphe: ?string,
     *     options: list<array{valeur: string, libelle: string, glyphe: bool}>,
     *     bonne: string
     * }>
     */
    public static function serie(City $ville, int $graine): array
    {
        $connus = AlphabetDesScribes::pour($ville);
        $hasard = new Randomizer(new Mt19937($graine));
        $types = [self::GLYPHE_VERS_SON, self::SON_VERS_GLYPHE, self::OBJET_VERS_SON];

        // Des signes tous différents tant que la ville en connaît assez ; au-delà
        // on repart du début du tirage.
        $ordre = $hasard->shuffleArray($connus);
        $questions = [];

        for ($rang = 0; $rang < self::QUESTIONS; ++$rang) {
            $signe = $ordre[$rang % \count($ordre)];
            $questions[] = self::question($types[$rang % \count($types)], $signe, $connus, $hasard);
        }

        return $questions;
    }

    /**
     * @param list<SigneAlphabetique> $connus
     *
     * @return array{type: string, enonce: string, glyphe: ?string, options: list<array{valeur: string, libelle: string, glyphe: bool}>, bonne: string}
     */
    private static function question(string $type, SigneAlphabetique $signe, array $connus, Randomizer $hasard): array
    {
        $autres = array_values(array_filter($connus, static fn (SigneAlphabetique $s): bool => $s !== $signe));
        $autres = $hasard->shuffleArray($autres);
        $propositions = $hasard->shuffleArray([$signe, ...\array_slice($autres, 0, self::PROPOSITIONS - 1)]);

        $enGlyphes = self::SON_VERS_GLYPHE === $type;

        return [
            'type' => $type,
            'enonce' => match ($type) {
                self::GLYPHE_VERS_SON => 'Quel son note ce signe ?',
                self::SON_VERS_GLYPHE => \sprintf('Quel signe note le son « %s » ?', $signe->translitteration()),
                default => \sprintf('Que note le signe « %s » ?', mb_strtolower($signe->objet())),
            },
            'glyphe' => self::GLYPHE_VERS_SON === $type ? $signe->signe() : null,
            'options' => array_values(array_map(
                static fn (SigneAlphabetique $s): array => [
                    'valeur' => $s->value,
                    'libelle' => $enGlyphes ? $s->signe() : \sprintf('%s — %s', $s->translitteration(), $s->son()),
                    'glyphe' => $enGlyphes,
                ],
                $propositions,
            )),
            'bonne' => $signe->value,
        ];
    }

    /**
     * Corrige une série.
     *
     * @param array<int|string, mixed> $reponses la valeur choisie, par rang de question
     *
     * @return array{bonnes: int, total: int, reussie: bool, recompense: int, corrections: list<string>}
     */
    public function repondre(GameSave $partie, int $graine, array $reponses): array
    {
        $ville = $partie->getVille();
        $serie = self::serie($ville, $graine);
        $bonnes = 0;
        $corrections = [];

        foreach ($serie as $rang => $question) {
            $donnee = $reponses[$rang] ?? null;

            if (\is_string($donnee) && $donnee === $question['bonne']) {
                ++$bonnes;

                continue;
            }

            $signe = SigneAlphabetique::from($question['bonne']);
            $corrections[] = \sprintf(
                '%s %s se lit « %s » (%s).',
                $signe->signe(),
                mb_strtolower($signe->objet()),
                $signe->translitteration(),
                $signe->son(),
            );
        }

        $reussie = $bonnes >= self::SEUIL_DE_REUSSITE;
        $recompense = 0;

        if ($reussie && !$ville->exerciceDesSonsRecompenseAuCycle($partie->getCycle())) {
            $ville->marquerExerciceDesSonsRecompense($partie->getCycle());
            $ville->crediterRessources([Ressource::Deben->value => self::RECOMPENSE_EN_DEBEN]);
            $recompense = self::RECOMPENSE_EN_DEBEN;
        }

        $this->entityManager->flush();

        return [
            'bonnes' => $bonnes,
            'total' => \count($serie),
            'reussie' => $reussie,
            'recompense' => $recompense,
            'corrections' => $corrections,
        ];
    }
}
