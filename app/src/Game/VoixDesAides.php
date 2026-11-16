<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;

/**
 * Le visage des aides que le jeu offre déjà (lots I1 et I5 du plan « dialogues-indices »).
 *
 * L'Oraculaire écarte une mauvaise réponse, le Déchiffreur et Thot ouvrent des signes de plus : c'était
 * muet. Ici ils parlent — **sans rien ajouter à ce qu'ils font**. Chaque phrase se calcule depuis la
 * même source que l'effet (`Enigmes::propositionsEcartees()`, `CleDeLecture::signesDuDechiffreur()`),
 * jamais depuis une seconde logique ; et l'aide ne parle que si elle agit : sans chef en poste, la
 * voix se tait. Le doc 10 le veut ainsi — les aides sont des spécialités de chef, pas un conseil
 * gratuit.
 */
final readonly class VoixDesAides
{
    public function __construct(private Enigmes $enigmes)
    {
    }

    /**
     * L'Oraculaire souffle ce qui n'est **pas** la réponse — jamais la réponse. Une seule phrase, même
     * gabarit pour toute énigme, et aucune ne se dit si l'Oraculaire n'écarte rien.
     */
    public function souffle(GameSave $partie, Enigme $enigme): ?string
    {
        $ecartees = $this->enigmes->propositionsEcartees($partie, $enigme);

        if ([] === $ecartees) {
            return null;
        }

        $chef = EffetDeChef::chefDeSpecialite($partie->getVille(), TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire, $partie->getCycle());

        return \sprintf(
            '%s souffle : ce n\'est pas « %s ».',
            self::nomDe($chef?->getPrenom(), 'l\'oraculaire'),
            implode(' », ni « ', $ecartees),
        );
    }

    /**
     * Qui a appris quoi à la Maison des scribes : une ligne par aide qui agit, avec le nombre de signes
     * qu'elle ouvre — le même que celui que compte la clé.
     *
     * @return list<string>
     */
    public static function signesAppris(City $ville, int $cycle): array
    {
        $lignes = [];
        $deChiffreur = CleDeLecture::signesDuDechiffreur($ville, $cycle);

        if ($deChiffreur > 0) {
            $chef = EffetDeChef::chefDeSpecialite($ville, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur, $cycle);
            $lignes[] = \sprintf('%s vous a appris %s.', self::nomDe($chef?->getPrenom(), 'le déchiffreur'), self::signes($deChiffreur));
        }

        $deThot = CleDeLecture::signesDeThot($ville);

        if ($deThot > 0) {
            $lignes[] = \sprintf('Thot vous éclaire : %s.', self::signes($deThot));
        }

        return $lignes;
    }

    /**
     * L'explication d'une énigme, **dite par le chef de la Maison des scribes** s'il y en a un.
     * Le contenu est celui de l'énigme, mot pour mot ; seule la voix s'y ajoute devant. Elle tombe
     * dans les deux cas, juste ou faux — c'est le gain de l'énigme (doc 10).
     */
    public static function explique(City $ville, string $explication): string
    {
        $chefs = $ville->chefsDe(TypeDeBatiment::MaisonDesScribes);
        $prenom = null !== ($chefs[0] ?? null) ? $chefs[0]->getPrenom() : null;

        return null === $prenom
            ? $explication
            : \sprintf('%s, chef de la Maison des scribes, l\'explique : %s', $prenom, $explication);
    }

    /**
     * « Hori l'oraculaire », ou « L'oraculaire » quand le chef n'a pas encore de prénom : la phrase
     * reste française dans les deux cas.
     */
    private static function nomDe(?string $prenom, string $fonction): string
    {
        return null === $prenom ? ucfirst($fonction) : $prenom.' '.$fonction;
    }

    private static function signes(int $nombre): string
    {
        return \sprintf('%d signe%s de plus', $nombre, $nombre > 1 ? 's' : '');
    }
}
