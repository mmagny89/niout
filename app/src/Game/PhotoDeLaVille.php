<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;

/**
 * Une photographie de la ville : qui est où, et ce qui s'y trame, à un instant.
 *
 * Elle sert à deux choses, et c'est ce qui en fait un seul objet. **Au début
 * d'une quinzaine**, elle est l'instantané que la trace garde pour pouvoir la
 * rejouer ; **à tout moment**, elle est la liste des silhouettes que
 * `GensDeLaVille` pose sur la ville vue d'en haut. Deux photographies — avant,
 * après — se comparent pour en tirer les faits (`DifferenceDeQuinzaine`) : la
 * méthode du récapitulatif, qui ne lit jamais un texte pour savoir ce qui s'est
 * passé.
 *
 * **Du JSON, rien d'autre** : des scalaires et des tableaux, pour tenir dans une
 * colonne et se relire après un changement de code. Elle ne lit que des règles
 * existantes (`Effectifs`, `Maisonnees`, `Medjay::estDisponible()`) et n'en pose
 * aucune.
 *
 * @phpstan-type CandidatDeFigure array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool}
 * @phpstan-type Photo array{
 *     cycle: int,
 *     jours: int,
 *     saison: ?string,
 *     fete: ?array{cle: string, libelle: string},
 *     candidats: list<CandidatDeFigure>,
 *     chantiers: list<array{id: int, lieu: string}>,
 *     fabrications: list<array{id: int, lieu: string}>,
 *     convois: list<array{id: int, partenaire: string, quinzaines: int}>,
 *     expeditions: list<array{id: int}>,
 *     epidemie: bool,
 *     malades: int,
 *     maisons: int,
 *     chefs: list<array{cle: string, libelle: string}>,
 *     medjays: list<array{cle: string, libelle: string, disponible: bool}>,
 *     rival: ?array{cle: string, libelle: string},
 *     batiments?: list<string>,
 *     temoignages?: list<string>,
 * }
 */
final readonly class PhotoDeLaVille
{
    /**
     * Combien de silhouettes au plus : la liste d'une photographie peut être plus longue,
     * la ville et le film n'en montrent pas davantage — et les conversations ne se tiennent
     * qu'entre elles.
     */
    public const int PLAFOND_DE_FIGURES = 40;

    public const int MAISONS_MONTREES = 6;

    public const int TRAVAILLEURS_PAR_BATIMENT = 3;

    public const int SANS_POSTE_MONTRES = 3;

    public function __construct(
        private Commerce $commerce,
    ) {
    }

    /**
     * @return Photo
     */
    public function prendre(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();
        $date = $partie->dateDeJeu();
        $postes = Effectifs::repartir($ville, $cycle);
        $bilan = Effectifs::bilan($ville, $cycle);
        $maisons = Maisonnees::repartir($ville);
        $fete = FeteCalendaire::pour($date);
        $rival = $ville->getRival();

        $candidats = [[
            'cle' => 'famille',
            'role' => RoleDeFigure::ChefDeFamille->value,
            'libelle' => self::nomDuChefDeFamille($partie),
            'lieu' => TypeDeBatiment::ResidenceFamiliale->value,
            'couchee' => false,
        ]];
        $chefs = [];

        foreach (self::chefsEnPoste($partie) as $chef) {
            $libelle = self::libelleDuChef($chef);
            $chefs[] = ['cle' => 'chef-'.$chef->getId(), 'libelle' => $libelle];
            $candidats[] = [
                'cle' => 'chef-'.$chef->getId(),
                'role' => RoleDeFigure::Chef->value,
                'libelle' => $libelle,
                'lieu' => $chef->getType()->value,
                'couchee' => false,
            ];
        }

        $medjays = [];

        foreach ($ville->getMedjays() as $medjay) {
            $disponible = $medjay->estDisponible($cycle);
            $libelle = self::libelleDuMedjay($medjay, $cycle);
            $medjays[] = ['cle' => 'medjay-'.$medjay->getId(), 'libelle' => $libelle, 'disponible' => $disponible];
            $candidats[] = [
                'cle' => 'medjay-'.$medjay->getId(),
                'role' => RoleDeFigure::Medjay->value,
                'libelle' => $libelle,
                'lieu' => ($disponible ? TypeDeBatiment::Caserne : TypeDeBatiment::QuartierDHabitation)->value,
                'couchee' => !$disponible,
            ];
        }

        if (null !== $rival) {
            $candidats[] = [
                'cle' => 'rival',
                'role' => RoleDeFigure::Rival->value,
                'libelle' => $rival->getNom(),
                'lieu' => TypeDeBatiment::Marche->value,
                'couchee' => false,
            ];
        }

        // Un bâtiment à l'arrêt n'a personne : c'est une information vraie.
        foreach ($postes as $cle => $poste) {
            $type = TypeDeBatiment::from($cle);

            for ($i = 0; $i < min($poste['affectes'], self::TRAVAILLEURS_PAR_BATIMENT); ++$i) {
                $candidats[] = [
                    'cle' => 'poste-'.$cle,
                    'role' => RoleDeFigure::Travailleur->value,
                    'libelle' => \sprintf('Travailleur — %s', $type->libelle()),
                    'lieu' => $cle,
                    'couchee' => false,
                ];
            }
        }

        for ($i = 0; $i < min($bilan['oisifs'], self::SANS_POSTE_MONTRES); ++$i) {
            $candidats[] = [
                'cle' => 'sans-poste',
                'role' => RoleDeFigure::SansPoste->value,
                'libelle' => 'Bras sans poste',
                'lieu' => TypeDeBatiment::Marche->value,
                'couchee' => false,
            ];
        }

        foreach (self::maisonsMontrees($maisons) as $rang) {
            $candidats[] = [
                'cle' => 'maison-'.($rang + 1),
                'role' => RoleDeFigure::Foyer->value,
                'libelle' => \sprintf('Maison %d', $rang + 1),
                'lieu' => TypeDeBatiment::QuartierDHabitation->value,
                'couchee' => \in_array(Maisonnees::MALADE, $maisons[$rang], true),
            ];
        }

        $chantiers = [];

        foreach ($ville->getChantiers() as $chantier) {
            $chantiers[] = ['id' => (int) $chantier->getId(), 'lieu' => $chantier->getType()->value];
        }

        $fabrications = [];

        foreach ($ville->getOrdresDeFabrication() as $ordre) {
            $fabrications[] = ['id' => (int) $ordre->getId(), 'lieu' => $ordre->getBatiment()->value];
        }

        $convois = [];

        foreach ($ville->getRoutesCommerciales() as $route) {
            $nom = $this->commerce->partenaireDe($partie, $route->getPartenaire())->nom ?? $route->getPartenaire();

            foreach ($route->getConvois() as $convoi) {
                $convois[] = ['id' => (int) $convoi->getId(), 'partenaire' => $nom, 'quinzaines' => $convoi->getQuinzainesAvantRetour()];
            }
        }

        $expeditions = [];

        foreach ($ville->getExpeditions() as $expedition) {
            $expeditions[] = ['id' => (int) $expedition->getId()];
        }

        return [
            'cycle' => $cycle,
            'jours' => $date->estJoursEpagomenes() ? 5 : 15,
            'saison' => $date->saison?->value,
            'fete' => null === $fete ? null : ['cle' => $fete->value, 'libelle' => $fete->libelle()],
            'candidats' => $candidats,
            'chantiers' => $chantiers,
            'fabrications' => $fabrications,
            'convois' => $convois,
            'expeditions' => $expeditions,
            'epidemie' => $ville->estFrappeeParUneEpidemie(),
            'malades' => $ville->malades(),
            'maisons' => \count($maisons),
            'chefs' => $chefs,
            'medjays' => $medjays,
            'rival' => null === $rival ? null : ['cle' => 'rival', 'libelle' => $rival->getNom()],
            // Les bâtiments debout, la Résidence comprise : de quoi savoir où l'on peut aller.
            // Absent des traces d'avant ce champ — la chorégraphie s'en passe alors.
            'batiments' => self::batimentsDresses($partie),
            // Les témoignages que les dossiers contiennent, par la valeur de l'indice : la
            // comparaison de deux photographies dit lesquels l'émissaire a rapportés.
            'temoignages' => self::temoignagesDesDossiers($partie),
        ];
    }

    /**
     * Les bâtiments dressés, par la valeur de `TypeDeBatiment` : ceux que la ville a
     * bâtis, et la Résidence, qui est là dès le premier jour.
     *
     * @return list<string>
     */
    public static function batimentsDresses(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $dresses = [];

        foreach (TypeDeBatiment::cases() as $type) {
            if (null !== $ville->batimentDeType($type) || $type->estLeBatimentDeDepart()) {
                $dresses[] = $type->value;
            }
        }

        return $dresses;
    }

    /**
     * Les indices de **témoignage** que les dossiers contiennent.
     *
     * @return list<string>
     */
    public static function temoignagesDesDossiers(GameSave $partie): array
    {
        $temoignages = [];

        foreach ($partie->getVille()->getDossiers() as $dossier) {
            foreach ($dossier->indices() as $indice) {
                if (SourceDIndice::Temoignage === $indice->source()) {
                    $temoignages[] = $indice->value;
                }
            }
        }

        return $temoignages;
    }

    /**
     * Les chefs qui tiennent réellement leur poste : un chef embauché à la
     * quinzaine qui vient n'est pas encore à l'ouvrage.
     *
     * @return list<Employee>
     */
    public static function chefsEnPoste(GameSave $partie): array
    {
        return array_values(array_filter(
            $partie->getVille()->getEmployes()->toArray(),
            static fn (Employee $chef): bool => $chef->estEnPoste($partie->getCycle()),
        ));
    }

    public static function nomDuChefDeFamille(GameSave $partie): string
    {
        return $partie->getFamille()->getChefDeFamille() ?? 'Le chef de la famille';
    }

    public static function libelleDuChef(Employee $chef): string
    {
        $poste = \sprintf('chef (%s)', $chef->getType()->libelle());

        return null !== $chef->getPrenom()
            ? \sprintf('%s — %s', $chef->getPrenom(), $poste)
            : ucfirst($poste);
    }

    public static function libelleDuMedjay(Medjay $medjay, int $cycle): string
    {
        $base = null !== $medjay->getPrenom()
            ? \sprintf('%s — %s', $medjay->getPrenom(), $medjay->getSpecialisation()->libelle())
            : $medjay->getSpecialisation()->libelle();

        return $medjay->estDisponible($cycle)
            ? $base
            : \sprintf('%s (blessé, de retour à la quinzaine %d)', $base, (int) $medjay->getRetabliAuCycle());
    }

    /**
     * Quelles maisons montrer : jusqu'à six, **régulièrement réparties** parmi
     * toutes, pour que les malades du fond du quartier ne soient pas oubliés au
     * profit des premières maisons.
     *
     * @param list<list<string>> $maisons
     *
     * @return list<int> les rangs retenus
     */
    public static function maisonsMontrees(array $maisons): array
    {
        $nombre = \count($maisons);

        if (0 === $nombre) {
            return [];
        }

        $montrees = min($nombre, self::MAISONS_MONTREES);
        $rangs = [];

        for ($i = 0; $i < $montrees; ++$i) {
            $rangs[] = intdiv($i * $nombre, $montrees);
        }

        return $rangs;
    }
}
