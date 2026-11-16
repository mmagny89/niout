<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * Range un message de la partie (une phrase ou deux, écrites par le jeu) en carte : un titre, un détail, un
 * pictogramme — comme le récapitulatif de la quinzaine.
 *
 * **Le texte reste entier.** Ce qui sépare le titre du détail (un `:` ou un `.`) n'est pas perdu : la
 * fonction le rend dans `liaison`, que le gabarit pose en `sr-only` — le lecteur d'écran lit la phrase
 * d'origine, et seul l'œil voit deux étages. Aucun message n'est réécrit ici.
 *
 * Le pictogramme se devine à des mots du message : c'est de la décoration, un message qui n'en trouve pas
 * reçoit celui de l'amélioration. Un pictogramme absent du disque n'est pas une erreur
 * (`image_d_interface`).
 */
final class MessagesDeJeuExtension
{
    /**
     * Le premier motif qui correspond l'emporte.
     */
    private const array ICONES = [
        '/expédition|éclaireur|émissaire|prospecteur|reconnaissance|filets/iu' => 'expedition',
        '/chantier|bâtir|bâti|maçon|sera prêt/iu' => 'chantier',
        '/maisonnée|bras|bouches|habitant|naissance|s\'installe/iu' => 'habitants',
        '/offrande|faveur|divinité|dieu/iu' => 'offrande',
        '/marché|vend|achet|échange|prix|étal/iu' => 'echange',
        '/chef|candidat|annonce|embauch/iu' => 'chef',
        '/fouill|énigme|indice|signes|inscription|enquête/iu' => 'enigme',
    ];

    /**
     * @return array{titre: string, detail: ?string, liaison: string, icone: string}
     */
    #[AsTwigFunction('message_de_jeu')]
    public function presenter(mixed $message, string $type = 'succes'): array
    {
        $texte = trim((string) (\is_scalar($message) ? $message : ''));
        $titre = $texte;
        $detail = null;
        $liaison = '';

        // Deux phrases : la première en titre. Sinon « titre : détail », quand le titre est court.
        if (1 === preg_match('/^(.+?)\.\s+(?!»)(\S.*)$/su', $texte, $m)) {
            [$titre, $detail, $liaison] = [$m[1], $m[2], '. '];
        } elseif (1 === preg_match('/^([^:]{3,60}?)\s:\s(\S.*)$/su', $texte, $m)) {
            [$titre, $detail, $liaison] = [$m[1], $m[2], ' : '];
        }

        return [
            'titre' => $titre,
            'detail' => $detail,
            'liaison' => $liaison,
            'icone' => 'erreur' === $type ? 'danger' : $this->icone($texte),
        ];
    }

    private function icone(string $texte): string
    {
        foreach (self::ICONES as $motif => $icone) {
            if (1 === preg_match($motif, $texte)) {
                return $icone;
            }
        }

        return 'amelioration';
    }
}
