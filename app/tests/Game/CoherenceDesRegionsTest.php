<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\CataloguePartenaires;
use App\Game\Divinite;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use PHPUnit\Framework\TestCase;

/**
 * Ce que la géographie d'une région doit rendre possible.
 *
 * Une mission est un jeu de paramètres — bords, ressources, partenaires — et
 * rien ne relie ces jeux entre eux : chacun peut être juste de son côté sans
 * que l'ensemble le soit. C'est ainsi qu'une mission s'est retrouvée avec pour
 * seul débouché une route fluviale dans une région sans une goutte d'eau, donc
 * sans commerce possible du tout. Ces contrôles valent pour les dix.
 */
final class CoherenceDesRegionsTest extends TestCase
{
    /**
     * **Aucune région n'est murée.** Le commerce est le débouché de tout ce
     * qu'on extrait au-delà de ses propres chantiers ; une mission dont aucune
     * route n'est atteignable joue avec un système en moins, sans que rien ne
     * le dise.
     */
    public function testChaqueMissionPeutOuvrirAuMoinsUneRoute(): void
    {
        foreach ($this->missions() as $mission) {
            $atteignables = array_filter(
                $this->partenaires($mission),
                fn (TypeDeRoute $route): bool => $this->routeAtteignable($route, $mission),
            );

            self::assertNotEmpty(
                $atteignables,
                \sprintf(
                    'Mission %d (%s) : aucune route ouvrable. Ses partenaires demandent %s, et la région n\'a pas de quoi les armer.',
                    $mission->numero,
                    $mission->ville,
                    implode(', ', array_map(static fn (TypeDeRoute $r): string => $r->libelle(), $this->partenaires($mission))),
                ),
            );
        }
    }

    /**
     * **Une route d'eau suppose de l'eau.** Le type de route décide du bâtiment
     * — le Port pour tout ce qui flotte —, et le Port exige un point d'eau
     * adjacent à la ville : proposer un partenaire fluvial dans une oasis
     * affiche une offre que rien ne peut jamais débloquer.
     */
    public function testUnPartenaireParEauSupposeUneRegionMouillee(): void
    {
        foreach ($this->missions() as $mission) {
            foreach ($this->partenaires($mission) as $route) {
                if (TypeDeBatiment::Port !== $route->batiment()) {
                    continue;
                }

                self::assertTrue(
                    $mission->geographie->aUnPointDEau(),
                    \sprintf(
                        'Mission %d (%s) : une route %s sans un point d\'eau où dresser le Port.',
                        $mission->numero,
                        $mission->ville,
                        mb_strtolower($route->libelle()),
                    ),
                );
            }
        }
    }

    /**
     * **Un dieu sans prise sur une région le déclare.** L'inverse — un dieu
     * déclaré inerte alors que son domaine existe là — priverait le joueur d'un
     * effet auquel il a droit, et refuserait son offrande pour rien.
     */
    public function testLesDieuxSansDomaineLeSontPourUneRaisonVerifiable(): void
    {
        foreach ($this->missions() as $mission) {
            $geographie = $mission->geographie;

            self::assertSame(
                !$geographie->connaitLaCrue(),
                Divinite::Hapi->estSansDomaineIci($geographie),
                \sprintf('Mission %d (%s) : Hâpi et la crue ne s\'accordent pas.', $mission->numero, $mission->ville),
            );

            self::assertSame(
                !$geographie->aUnPointDEau(),
                Divinite::Sobek->estSansDomaineIci($geographie),
                \sprintf('Mission %d (%s) : Sobek et l\'eau ne s\'accordent pas.', $mission->numero, $mission->ville),
            );
        }
    }

    /**
     * Un dieu qu'aucune géographie ne prive reste offrable partout : la règle
     * est régionale, jamais un retrait pur et simple du panthéon.
     */
    public function testSeulsHapiEtSobekDependentDeLaGeographie(): void
    {
        foreach ($this->missions() as $mission) {
            foreach (Divinite::pantheon() as $divinite) {
                if (\in_array($divinite, [Divinite::Hapi, Divinite::Sobek], true)) {
                    continue;
                }

                self::assertFalse(
                    $divinite->estSansDomaineIci($mission->geographie),
                    \sprintf('%s ne devrait dépendre d\'aucune géographie.', $divinite->libelle()),
                );
            }
        }
    }

    /**
     * Le Port se dresse là où il y a de l'eau, et nulle part ailleurs : c'est
     * ce qui rend une région sans eau incapable de pêcher comme de naviguer.
     */
    private function routeAtteignable(TypeDeRoute $route, Mission $mission): bool
    {
        return TypeDeBatiment::Port !== $route->batiment() || $mission->geographie->aUnPointDEau();
    }

    /**
     * @return list<TypeDeRoute>
     */
    private function partenaires(Mission $mission): array
    {
        return array_map(
            static fn ($partenaire): TypeDeRoute => $partenaire->route,
            (new CataloguePartenaires())->pourLaMission($mission->numero),
        );
    }

    /**
     * @return list<Mission>
     */
    private function missions(): array
    {
        return array_values((new MissionCatalogue())->toutes());
    }
}
