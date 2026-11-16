<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui s'est passé pendant une quinzaine, déduit de deux photographies de la
 * ville : au début, et à la fin (`PhotoDeLaVille`).
 *
 * **On ne lit jamais un texte pour savoir ce qui s'est passé** — c'est la leçon du
 * récapitulatif, qui compare deux états plutôt que de relire le journal du cycle.
 * Les services du cycle n'ont donc rien à annoncer : un chantier qui a disparu des
 * chantiers s'est achevé, un convoi qui n'est plus là est rentré, un chef qui n'est
 * plus en poste est parti. Ce qui n'est pas visible dans l'état — qui a porté quoi,
 * quel jour — n'est pas un fait, et la chorégraphie ne l'invente pas.
 *
 * Pur : deux tableaux entrent, une liste sort.
 *
 * @phpstan-type Fait array{type: TypeDeFait, lieu: ?string, acteur: ?string, quantite: ?int, details: array<string, mixed>}
 */
final class DifferenceDeQuinzaine
{
    /**
     * @param array<string, mixed> $avant une `PhotoDeLaVille`, prise au début de la quinzaine
     * @param array<string, mixed> $apres une `PhotoDeLaVille`, prise à sa fin
     *
     * @return list<Fait>
     */
    public static function faits(array $avant, array $apres): array
    {
        $faits = [];

        // Un chantier encore là a avancé ; un chantier disparu s'est achevé.
        $restants = array_column($apres['chantiers'], 'lieu', 'id');

        foreach ($avant['chantiers'] as $chantier) {
            $faits[] = self::fait(
                isset($restants[$chantier['id']]) ? TypeDeFait::ChantierAvance : TypeDeFait::ChantierAcheve,
                $chantier['lieu'],
                'chantier-'.$chantier['id'],
            );
        }

        $restants = array_column($apres['fabrications'], 'lieu', 'id');

        foreach ($avant['fabrications'] as $ordre) {
            $faits[] = self::fait(
                isset($restants[$ordre['id']]) ? TypeDeFait::FabricationAvance : TypeDeFait::FabricationAchevee,
                $ordre['lieu'],
                'ordre-'.$ordre['id'],
            );
        }

        // Une caravane rentrée **repart plutôt que d'être recréée** (`Commerce`) :
        // même identifiant, mais un retour plus lointain qu'avant.
        $apresConvois = [];

        foreach ($apres['convois'] as $convoi) {
            $apresConvois[$convoi['id']] = $convoi;
        }

        $vusAvant = [];

        foreach ($avant['convois'] as $convoi) {
            $vusAvant[$convoi['id']] = true;
            $acteur = 'convoi-'.$convoi['id'];
            $details = ['partenaire' => $convoi['partenaire']];

            if (!isset($apresConvois[$convoi['id']])) {
                $faits[] = self::fait(TypeDeFait::ConvoiRentre, null, $acteur, null, $details);
            } elseif ($apresConvois[$convoi['id']]['quinzaines'] > $convoi['quinzaines']) {
                $faits[] = self::fait(TypeDeFait::ConvoiRentre, null, $acteur, null, $details);
                $faits[] = self::fait(TypeDeFait::ConvoiParti, null, $acteur, null, $details);
            }
        }

        foreach ($apresConvois as $id => $convoi) {
            if (!isset($vusAvant[$id])) {
                $faits[] = self::fait(TypeDeFait::ConvoiParti, null, 'convoi-'.$id, null, ['partenaire' => $convoi['partenaire']]);
            }
        }

        $restantes = array_column($apres['expeditions'], 'id');

        foreach ($avant['expeditions'] as $expedition) {
            $faits[] = self::fait(
                \in_array($expedition['id'], $restantes, true) ? TypeDeFait::ExpeditionEnRoute : TypeDeFait::ExpeditionRentree,
                null,
                'expedition-'.$expedition['id'],
            );
        }

        if (!$avant['epidemie'] && $apres['epidemie']) {
            $faits[] = self::fait(TypeDeFait::EpidemieEclate, null, null, $apres['malades']);
        } elseif ($avant['epidemie'] && !$apres['epidemie']) {
            $faits[] = self::fait(TypeDeFait::EpidemieFinie);
        }

        $chefsApres = array_column($apres['chefs'], 'cle');

        foreach ($avant['chefs'] as $chef) {
            if (!\in_array($chef['cle'], $chefsApres, true)) {
                $faits[] = self::fait(TypeDeFait::ChefParti, null, $chef['cle'], null, ['libelle' => $chef['libelle']]);
            }
        }

        if (null === $avant['rival'] && null !== $apres['rival']) {
            $faits[] = self::fait(TypeDeFait::RivalArrive, null, 'rival', null, ['libelle' => $apres['rival']['libelle']]);
        } elseif (null !== $avant['rival'] && null === $apres['rival']) {
            $faits[] = self::fait(TypeDeFait::RivalParti, null, 'rival', null, ['libelle' => $avant['rival']['libelle']]);
        }

        $medjaysApres = [];

        foreach ($apres['medjays'] as $medjay) {
            $medjaysApres[$medjay['cle']] = $medjay;
        }

        foreach ($avant['medjays'] as $medjay) {
            if ($medjay['disponible'] && isset($medjaysApres[$medjay['cle']]) && !$medjaysApres[$medjay['cle']]['disponible']) {
                $faits[] = self::fait(TypeDeFait::MedjayBlesse, null, $medjay['cle'], null, ['libelle' => $medjaysApres[$medjay['cle']]['libelle']]);
            }
        }

        // Un témoignage de plus dans un dossier : l'émissaire en a rapporté un. Les traces d'avant
        // ce champ n'en connaissent aucun, donc n'en inventent aucun.
        foreach (array_diff($apres['temoignages'] ?? [], $avant['temoignages'] ?? []) as $indice) {
            $faits[] = self::fait(TypeDeFait::TemoignageRecueilli, null, null, null, ['indice' => $indice]);
        }

        if (null !== $avant['fete']) {
            $faits[] = self::fait(TypeDeFait::Fete, null, null, null, ['libelle' => $avant['fete']['libelle'], 'fete' => $avant['fete']['cle']]);
        }

        return $faits;
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return Fait
     */
    private static function fait(TypeDeFait $type, ?string $lieu = null, ?string $acteur = null, ?int $quantite = null, array $details = []): array
    {
        return ['type' => $type, 'lieu' => $lieu, 'acteur' => $acteur, 'quantite' => $quantite, 'details' => $details];
    }
}
