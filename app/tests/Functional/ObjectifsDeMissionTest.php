<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\CataloguePartenaires;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\MissionCatalogue;
use App\Game\ObjectifDeMission;
use App\Game\ObjectifsDeMission;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDObjectif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les objectifs de mission (lot 8.0).
 *
 * Ce qui se vérifie ici tient en une phrase : **aucun objectif ne doit être
 * hors d'atteinte**. C'est l'exercice d'`OuvertureDePartieTest`, appliqué à la
 * fin d'une mission plutôt qu'à son début — chacun peut être juste de son côté
 * sans que l'ensemble le soit.
 */
final class ObjectifsDeMissionTest extends WebTestCase
{
    /**
     * **Chaque mission en porte deux ou trois** (doc 09), et jamais zéro : une
     * mission sans objectif chiffré n'aurait que son fil rouge.
     */
    public function testChaqueMissionPorteDeuxOuTroisObjectifs(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            $objectifs = ObjectifsDeMission::pour($mission);

            self::assertGreaterThanOrEqual(2, \count($objectifs), \sprintf('Mission %d.', $mission->numero));
            self::assertLessThanOrEqual(3, \count($objectifs));

            foreach ($objectifs as $objectif) {
                self::assertNotSame('', $objectif->libelle());
                self::assertGreaterThan(0, $objectif->seuil);
            }
        }
    }

    /**
     * **Un objectif d'infrastructure ne dépasse jamais la borne régionale.**
     * `niveauMaxRegional` vaut `5 + difficulté` : demander au-delà rendrait
     * l'objectif littéralement inatteignable, et le joueur ne le découvrirait
     * qu'après avoir tout construit.
     */
    public function testAucunObjectifDInfrastructureNestHorsDatteinte(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach (ObjectifsDeMission::pour($mission) as $objectif) {
                if (TypeDObjectif::Infrastructure !== $objectif->type) {
                    continue;
                }

                self::assertNotNull($objectif->batiment);
                self::assertLessThanOrEqual(
                    5 + $mission->difficulte,
                    $objectif->seuil,
                    \sprintf('Mission %d : la borne régionale l\'interdit.', $mission->numero),
                );
                self::assertLessThanOrEqual(
                    $objectif->batiment->niveauMax(),
                    $objectif->seuil,
                    \sprintf('Mission %d : le bâtiment ne monte pas si haut.', $mission->numero),
                );
            }
        }
    }

    /**
     * **Une ressource demandée doit être obtenable dans la région** — sur le
     * terrain, ou par une route que la région ouvre.
     *
     * La nuance vient d'un cas réel : le doc 09 demande de l'or à Éléphantine,
     * quand le doc 08 place les mines d'or ailleurs. Les deux ont raison —
     * Éléphantine était un **poste douanier**, l'or de Nubie y transitait sans
     * qu'on l'y extraie. L'objectif tient donc, mais par le commerce, et c'est
     * exactement ce que Séthi Ier attendait de cette ville.
     *
     * Ce qui serait faux, en revanche, c'est de demander une ressource
     * qu'aucun chemin ne rend accessible.
     */
    public function testChaqueRessourceDemandeeEstObtenableDansSaRegion(): void
    {
        $partenaires = new CataloguePartenaires();

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach (ObjectifsDeMission::pour($mission) as $objectif) {
                if (TypeDObjectif::Ressource !== $objectif->type) {
                    continue;
                }

                self::assertNotNull($objectif->ressource);

                $surPlace = \in_array($objectif->ressource, $mission->geographie->ressourcesDeZone, true);
                $aLaVente = false;

                foreach ($partenaires->pourLaMission($mission->numero) as $partenaire) {
                    if (\in_array($objectif->ressource, $partenaire->vend, true)) {
                        $aLaVente = true;
                    }
                }

                self::assertTrue(
                    $surPlace || $aLaVente,
                    \sprintf(
                        'Mission %d : %s n\'est ni sur le terrain ni sur une route.',
                        $mission->numero,
                        $objectif->ressource->libelle(),
                    ),
                );
            }
        }
    }

    /**
     * **Les seuils croissent avec la difficulté**, sans exception : une
     * mission plus dure ne doit jamais demander moins qu'une plus facile.
     */
    public function testLesSeuilsMontentAvecLaDifficulte(): void
    {
        $parType = [];

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach (ObjectifsDeMission::pour($mission) as $objectif) {
                $parType[$objectif->type->value][$mission->difficulte] = $objectif->seuil;
            }
        }

        foreach ($parType as $type => $seuils) {
            ksort($seuils);
            $precedent = 0;

            foreach ($seuils as $seuil) {
                self::assertGreaterThanOrEqual($precedent, $seuil, \sprintf('Type %s.', $type));
                $precedent = $seuil;
            }
        }
    }

    /**
     * **Chaque type d'objectif a une mesure qui bouge**, et c'est vérifié une
     * par une plutôt que déclaré.
     *
     * C'est le garde-fou contre le piège d'`ajusterRenommee()` — un objectif
     * indexé sur une valeur que rien ne fait changer. Un drapeau « pas encore
     * mesuré » ne l'aurait pas évité : ce test-ci, si. Tout type ajouté au
     * pool tombera ici tant que rien ne le fait avancer.
     */
    public function testChaqueTypeDObjectifAUneMesureQuiBouge(): void
    {
        self::bootKernel();

        foreach (TypeDObjectif::cases() as $type) {
            $partie = $this->lancerPartie(\sprintf('mesure-%s@example.com', $type->value));
            $objectif = $this->unObjectifDe($type);

            $avant = $objectif->avancement($partie);
            $this->faireBouger($partie, $type);
            $apres = $objectif->avancement($partie);

            self::assertGreaterThan(
                $avant,
                $apres,
                \sprintf('Rien ne fait avancer un objectif de type « %s ».', $type->libelle()),
            );
        }
    }

    /**
     * Et les deux mesures ajoutées au lot 8.1 ne se confondent pas avec le
     * stock : **une ressource rapportée puis dépensée reste rapportée**, sans
     * quoi le joueur serait puni d'avoir joué.
     */
    public function testUneRessourceDepenseeResteRapportee(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rapportee@example.com');
        $ville = $partie->getVille();

        $ville->crediterRessources([Ressource::Calcaire->value => 40]);
        self::assertSame(40, $ville->ressourceRapportee(Ressource::Calcaire));

        $ville->debiterRessources([Ressource::Calcaire->value => 40]);

        self::assertSame(0, $ville->quantite(Ressource::Calcaire), 'Le stock est vide.');
        self::assertSame(40, $ville->ressourceRapportee(Ressource::Calcaire), 'Le compte, lui, tient.');
    }

    /**
     * Ce qui se mesure déjà se mesure vraiment : un objectif atteint doit se
     * voir comme atteint.
     */
    public function testUnObjectifAtteintSeVoitCommeAtteint(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('objectif@example.com');
        $ville = $partie->getVille();

        $infrastructure = new ObjectifDeMission(TypeDObjectif::Infrastructure, 3, batiment: TypeDeBatiment::Entrepot);
        self::assertFalse($infrastructure->estAtteint($partie));

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 3));
        self::assertTrue($infrastructure->estAtteint($partie));
        self::assertSame(3, $infrastructure->avancement($partie));

        $richesse = new ObjectifDeMission(TypeDObjectif::Richesse, 1_000);
        self::assertFalse($richesse->estAtteint($partie));
        $ville->crediterRessources([Ressource::Deben->value => 1_000]);
        self::assertTrue($richesse->estAtteint($partie));
    }

    /**
     * **Ce qui passe par le Marché compte au volume échangé**, comme ce qui
     * passe par une caravane (doc 09).
     */
    public function testUneVenteAuMarcheCompteAuVolumeEchange(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('marche-echange@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche, 1));
        $ville->crediterRessources([Ressource::Calcaire->value => 20]);

        self::assertSame(0, $ville->getValeurEchangee());

        $recette = static::getContainer()->get(Marche::class)->vendre($partie, Ressource::Calcaire, 10);

        self::assertGreaterThan(0, $recette);
        self::assertSame($recette, $ville->getValeurEchangee());
    }

    private function unObjectifDe(TypeDObjectif $type): ObjectifDeMission
    {
        return match ($type) {
            TypeDObjectif::Infrastructure => new ObjectifDeMission($type, 1, batiment: TypeDeBatiment::Entrepot),
            TypeDObjectif::Ressource => new ObjectifDeMission($type, 1, ressource: Ressource::Calcaire),
            default => new ObjectifDeMission($type, 1),
        };
    }

    private function faireBouger(GameSave $partie, TypeDObjectif $type): void
    {
        $ville = $partie->getVille();

        match ($type) {
            TypeDObjectif::Richesse => $ville->crediterRessources([Ressource::Deben->value => 50]),
            TypeDObjectif::Population => $ville->accueillir(3, 1, 0),
            TypeDObjectif::Renommee => $partie->getFamille()->ajusterRenommee(5),
            TypeDObjectif::Infrastructure => $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 2)),
            TypeDObjectif::Commerce => $ville->compterUnEchange(120),
            TypeDObjectif::Ressource => $ville->crediterRessources([Ressource::Calcaire->value => 30]),
        };
    }

    /**
     * L'écran : les objectifs sont là dès le premier jour (doc 09), le fil
     * rouge en tête puisqu'il est le seul obligatoire.
     */
    public function testLesObjectifsSAffichentDesLePremierJour(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('objectifs-ecran@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorExists('#panneau-mission');
        self::assertSelectorTextContains('#panneau-mission', 'Ahmôsis');
        self::assertSelectorTextContains('#panneau-mission', 'Résoudre le fil rouge');
        self::assertSelectorTextContains('#panneau-mission', 'Échanger pour');
    }

    private function lancerPartie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
