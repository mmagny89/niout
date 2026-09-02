<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Écrire un nom avec l'alphabet des scribes (doc 10).
 *
 * « Le jeu propose une transcription phonétique approximative en hiéroglyphes
 * réels à partir de cet alphabet, avec une explication claire : l'écriture
 * égyptienne ne notait pas les voyelles, la transcription est donc une
 * approximation conventionnelle — **exactement la démarche employée
 * aujourd'hui dans les musées** pour "écrire son prénom en hiéroglyphes". »
 *
 * **Ce n'est pas de l'égyptologie, et l'écran doit le dire.** Un scribe du
 * Nouvel Empire n'aurait pas écrit « Dupont » ainsi : il n'aurait pas écrit
 * les voyelles du tout, et aurait cherché des signes bilitères. Ce que le jeu
 * produit est la convention des cartouches de musée — utile pour apprendre à
 * reconnaître les signes, jamais une traduction.
 *
 * Deux choix de fond, tous deux conventionnels et tous deux dits à l'écran :
 *
 * - **les voyelles reçoivent les semi-voyelles** — le vautour pour *a*, le
 *   roseau pour *i* et *e*, le poussin pour *o* et *ou*. C'est ce que font les
 *   musées ; s'en tenir aux consonnes rendrait « Nakht » illisible ;
 * - **le l s'écrit avec le r** : l'égyptien du Nouvel Empire ne distinguait pas
 *   les deux, et `D21` est le substitut qu'emploient les grammaires.
 *
 * **Aucun signe n'est inventé pour boucher un trou** : un caractère sans
 * équivalent est écarté, et la transcription dit lequel.
 */
final readonly class TranscriptionDuNom
{
    /**
     * Les groupes de lettres qui valent un seul son, éprouvés avant les
     * lettres seules — « ch » n'est pas un c suivi d'un h, et « ou » n'est pas
     * un o suivi d'un u.
     *
     * @var array<string, list<SigneAlphabetique>>
     */
    private const array GROUPES = [
        'ch' => [SigneAlphabetique::BassinDEau],
        'sh' => [SigneAlphabetique::BassinDEau],
        'kh' => [SigneAlphabetique::Tamis],
        'ou' => [SigneAlphabetique::PoussinDeCaille],
        'ph' => [SigneAlphabetique::VipereACornes],
        'th' => [SigneAlphabetique::Pain],
        // Un x vaut deux sons, et s'écrit donc avec deux signes.
        'x' => [SigneAlphabetique::CorbeilleAAnse, SigneAlphabetique::LingePlie],
    ];

    /**
     * @var array<string, SigneAlphabetique>
     */
    private const array LETTRES = [
        'a' => SigneAlphabetique::VautourPercnoptere,
        'b' => SigneAlphabetique::Jambe,
        // Le c dur vaut k, le c doux vaut s : c'est le son qui décide, pas la
        // lettre. Voir `signeDuC()`.
        'd' => SigneAlphabetique::Main,
        'e' => SigneAlphabetique::RoseauFleuri,
        'f' => SigneAlphabetique::VipereACornes,
        'g' => SigneAlphabetique::SupportDeJarre,
        'h' => SigneAlphabetique::AbriEnRoseaux,
        'i' => SigneAlphabetique::RoseauFleuri,
        'j' => SigneAlphabetique::Cobra,
        'k' => SigneAlphabetique::CorbeilleAAnse,
        'l' => SigneAlphabetique::Bouche,
        'm' => SigneAlphabetique::Chouette,
        'n' => SigneAlphabetique::FiletDEau,
        'o' => SigneAlphabetique::PoussinDeCaille,
        'p' => SigneAlphabetique::Natte,
        'q' => SigneAlphabetique::FlancDeColline,
        'r' => SigneAlphabetique::Bouche,
        's' => SigneAlphabetique::LingePlie,
        't' => SigneAlphabetique::Pain,
        'u' => SigneAlphabetique::PoussinDeCaille,
        'v' => SigneAlphabetique::VipereACornes,
        'w' => SigneAlphabetique::PoussinDeCaille,
        'y' => SigneAlphabetique::DeuxTraits,
        'z' => SigneAlphabetique::LingePlie,
    ];

    /**
     * Le nom écrit signe par signe, et ce que la transcription a dû écarter.
     *
     * @return array{signes: list<SigneAlphabetique>, ecartes: list<string>}
     */
    public static function pour(string $nom): array
    {
        $lettres = self::simplifier($nom);
        $signes = [];
        $ecartes = [];
        $rang = 0;

        while ($rang < mb_strlen($lettres)) {
            $groupe = mb_substr($lettres, $rang, 2);

            if (isset(self::GROUPES[$groupe])) {
                $signes = [...$signes, ...self::GROUPES[$groupe]];
                $rang += 2;

                continue;
            }

            $lettre = mb_substr($lettres, $rang, 1);
            ++$rang;

            if (isset(self::GROUPES[$lettre])) {
                $signes = [...$signes, ...self::GROUPES[$lettre]];

                continue;
            }

            if ('c' === $lettre) {
                $signes[] = self::signeDuC(mb_substr($lettres, $rang, 1));

                continue;
            }

            if (isset(self::LETTRES[$lettre])) {
                $signes[] = self::LETTRES[$lettre];

                continue;
            }

            // Ni lettre ni groupe connu — un chiffre, un tiret, une apostrophe.
            // On l'écarte, et on le dit : forcer un signe serait mentir.
            if (!\in_array($lettre, $ecartes, true) && '' !== trim($lettre)) {
                $ecartes[] = $lettre;
            }
        }

        return ['signes' => $signes, 'ecartes' => $ecartes];
    }

    /**
     * Le nom écrit d'un trait, pour l'affichage.
     */
    public static function ecrire(string $nom): string
    {
        return implode('', array_map(
            static fn (SigneAlphabetique $s): string => $s->signe(),
            self::pour($nom)['signes'],
        ));
    }

    /**
     * **Le c suit le son, pas la lettre** : dur devant a, o, u et en fin de
     * mot, doux devant e, i et y. C'est la seule règle contextuelle de la
     * table, et elle évite d'écrire « Cécile » avec deux k.
     */
    private static function signeDuC(string $suivante): SigneAlphabetique
    {
        return \in_array($suivante, ['e', 'i', 'y'], true)
            ? SigneAlphabetique::LingePlie
            : SigneAlphabetique::CorbeilleAAnse;
    }

    /**
     * Minuscules, accents retirés : un é et un e s'écrivent du même roseau,
     * l'égyptien ne notant ni l'un ni l'autre.
     */
    private static function simplifier(string $nom): string
    {
        $sansAccent = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $nom);

        return false === $sansAccent ? mb_strtolower($nom) : $sansAccent;
    }
}
