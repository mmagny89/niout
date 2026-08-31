<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\CataloguePartenaires;
use App\Game\Commerce;
use App\Game\CycleAgricoleTerrestre;
use App\Game\Divinite;
use App\Game\EffetDeFaveur;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\QualiteDeCrue;
use App\Game\Ressource;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce que la faveur change réellement (lot 6.3).
 *
 * L'enjeu du lot n'est pas qu'un bonus existe, c'est **par où il passe**. Un
 * dieu qui multiplierait une chaîne déjà multipliée referait le double comptage
 * retiré au lot 4.5 ; ces tests vérifient donc autant le canal que l'effet.
 */
final class EffetDeFaveurTest extends KernelTestCase
{
    /**
     * **Hâpi n'ajoute pas un facteur à la récolte** : il infléchit le tirage
     * de la crue, d'un cran, et le hasard reste le hasard — une crue déjà
     * forte ne monte pas plus haut, il n'y a pas de cran au-dessus.
     */
    public function testHapiInflechitLaCrueSansCreerDeCrueImpossible(): void
    {
        self::bootKernel();
        $ville = $this->villeAvec('hapi@example.com', Divinite::Hapi, PalierDeFaveur::Favorable)->getVille();

        self::assertSame(QualiteDeCrue::Normale, EffetDeFaveur::crueInflechie($ville, QualiteDeCrue::Faible));
        self::assertSame(QualiteDeCrue::Forte, EffetDeFaveur::crueInflechie($ville, QualiteDeCrue::Normale));
        self::assertSame(QualiteDeCrue::Forte, EffetDeFaveur::crueInflechie($ville, QualiteDeCrue::Forte));

        // Et le modificateur de récolte reste seul sur sa chaîne : c'est la
        // crue qui a changé, pas le rendement du champ.
        self::assertSame(
            QualiteDeCrue::Forte->modificateurEnDixiemes(),
            EffetDeFaveur::crueInflechie($ville, QualiteDeCrue::Normale)->modificateurEnDixiemes(),
        );
    }

    /**
     * Un dieu neutre ne touche à rien, et c'est ce qui rend l'inaction sans
     * conséquence.
     */
    public function testUnDieuNeutreNeChangeRien(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('neutre-effet@example.com')->getVille();

        self::assertSame(QualiteDeCrue::Faible, EffetDeFaveur::crueInflechie($ville, QualiteDeCrue::Faible));
        self::assertSame(0, EffetDeFaveur::bonusDeChantier($ville));
        self::assertSame(0, EffetDeFaveur::remiseSurLAppel($ville));
        self::assertFalse(EffetDeFaveur::jachereRaccourcie($ville));
    }

    /**
     * **Ptah s'ajoute au facteur de saison**, dans la même unité, au lieu de
     * le multiplier. Ce qui se mesure ici est le chantier lui-même : il
     * s'achève plus tôt.
     *
     * Sur un **long** chantier, délibérément : les quinzaines sont entières,
     * et un Grenier de deux cycles ne distingue pas +30 % de rien du tout —
     * la leçon du lot 5.9, qui vaut pour toute cadence.
     */
    public function testPtahAbregeUnChantier(): void
    {
        self::bootKernel();
        $sansPtah = $this->quinzainesDeChantier('sans-ptah@example.com', null);
        $avecPtah = $this->quinzainesDeChantier('avec-ptah@example.com', PalierDeFaveur::Devoue);

        self::assertLessThan($sansPtah, $avecPtah, 'Ptah presse les travaux.');
    }

    /**
     * **Osiris agit sur le cycle, pas sur la gerbe** : le champ ne rend pas
     * davantage, il revient plus tôt. C'est ce qui évite d'empiler un
     * multiplicateur sur une chaîne qui en porte déjà.
     */
    public function testOsirisRaccourcitLaJachereSansGonflerLaRecolte(): void
    {
        self::bootKernel();
        $ville = $this->villeAvec('osiris@example.com', Divinite::Osiris, PalierDeFaveur::Favorable)->getVille();

        self::assertTrue(EffetDeFaveur::jachereRaccourcie($ville));
        self::assertSame(
            CycleAgricoleTerrestre::DUREE_TOTALE - 1,
            CycleAgricoleTerrestre::DUREE_SANS_JACHERE,
        );

        // Sur une même longueur de temps, plus de quinzaines de récolte…
        $sans = $this->quinzainesDeRecolte(jachereRaccourcie: false);
        $avec = $this->quinzainesDeRecolte(jachereRaccourcie: true);
        self::assertGreaterThan($sans, $avec);

        // …mais chacune rend exactement la même chose.
        self::assertSame(
            CycleAgricoleTerrestre::pourUneQuinzaine(4, false),
            CycleAgricoleTerrestre::pourUneQuinzaine(4, true),
            'Une récolte d\'Osiris n\'est pas plus grosse, elle est plus fréquente.',
        );
    }

    /**
     * **Amon-Rê rend la ville plus attirante** : appeler coûte moins cher.
     * Jamais gratuit — un appel reste un voyage.
     */
    public function testAmonReAllegeLeCoutDunAppel(): void
    {
        self::bootKernel();
        $partie = $this->villeAvec('amon@example.com', Divinite::AmonRe, PalierDeFaveur::Devoue);
        $temoin = $this->lancerPartie('amon-temoin@example.com');

        $appels = static::getContainer()->get(\App\Game\AppelDHabitants::class);

        self::assertLessThan($appels->cout($temoin), $appels->cout($partie));
        self::assertGreaterThanOrEqual(1, $appels->cout($partie));
    }

    /**
     * **Un dieu ajoute à ce que la renommée a ouvert, il ne l'ouvre pas.**
     * Une famille inconnue reste inconnue, quels que soient ses dieux.
     */
    public function testAmonReNeRemplacePasLaRenommee(): void
    {
        self::bootKernel();
        $partie = $this->villeAvec('amon-migration@example.com', Divinite::AmonRe, PalierDeFaveur::Devoue);

        self::assertSame(EffetDeFaveur::MIGRATION_DEVOUE, EffetDeFaveur::bonusDeMigration($partie->getVille()));
        // La renommée d'une famille neuve n'ouvre aucune migration spontanée :
        // le bonus s'ajoute à zéro, donc ne fait rien venir.
        self::assertSame(0, $partie->getFamille()->palier()->chanceDeMigrationSpontanee());
    }

    /**
     * **Sobek veille sur l'eau, et sur elle seule** : une piste caravanière ne
     * le regarde pas. Et un trajet ne descend jamais sous une quinzaine, sans
     * quoi la distance cesserait de décider de la fréquence des convois.
     */
    public function testSobekRaccourcitLEauEtPasLesPistes(): void
    {
        self::bootKernel();
        $partie = $this->villeAvec('sobek@example.com', Divinite::Sobek, PalierDeFaveur::Devoue);
        $ville = $partie->getVille();
        $catalogue = new CataloguePartenaires();

        $byblos = $catalogue->partenaire(1, 'byblos');
        $canaan = $catalogue->partenaire(1, 'canaan');
        $memphis = $catalogue->partenaire(1, 'memphis');
        self::assertNotNull($byblos);
        self::assertNotNull($canaan);
        self::assertNotNull($memphis);

        $commerce = static::getContainer()->get(Commerce::class);

        self::assertLessThan(
            $byblos->distanceEnQuinzaines,
            $commerce->trajetVers($byblos, $ville, $partie->getCycle()),
            'La mer se traverse plus vite sous Sobek.',
        );
        self::assertSame(
            $canaan->distanceEnQuinzaines,
            $commerce->trajetVers($canaan, $ville, $partie->getCycle()),
            'Une piste caravanière ne doit rien à Sobek.',
        );
        self::assertGreaterThanOrEqual(
            1,
            $commerce->trajetVers($memphis, $ville, $partie->getCycle()),
        );
    }

    /**
     * **Un dieu favorable ne pénalise jamais une production.** L'hostilité se
     * paie autrement — une crue moins généreuse —, jamais par un malus de
     * rendement : deux malus qui se multiplient sont ce qui a fait tomber la
     * chaîne alimentaire à 25 % au lot 4.4.
     */
    public function testLHostiliteNePenaliseJamaisUneProduction(): void
    {
        self::bootKernel();
        $ville = $this->villeAvec('hostile@example.com', Divinite::Ptah, PalierDeFaveur::Hostile)->getVille();

        self::assertTrue($ville->palierDe(Divinite::Ptah)->nuit());
        self::assertSame(0, EffetDeFaveur::bonusDeChantier($ville), 'Un dieu fâché ralentit-il ? Non : il cesse d\'aider.');
        self::assertSame(0, EffetDeFaveur::remiseSurLAppel($ville));
        self::assertFalse(EffetDeFaveur::jachereRaccourcie($ville));
    }

    /**
     * Sept dieux sur huit font désormais quelque chose. Seule Isis annonce
     * encore son inertie — elle attend le combat —, et **elle seule**, sans
     * quoi le panthéon promettrait à faux.
     */
    public function testSeuleIsisAnnonceEncoreSonInertie(): void
    {
        foreach (Divinite::pantheon() as $divinite) {
            if (Divinite::Isis === $divinite) {
                self::assertFalse($divinite->agitDeja());
                self::assertNotNull($divinite->attente());

                continue;
            }

            self::assertTrue($divinite->agitDeja(), \sprintf('%s agit.', $divinite->libelle()));
            self::assertNull($divinite->attente(), \sprintf('%s n\'a plus rien à faire attendre.', $divinite->libelle()));
        }
    }

    private function quinzainesDeRecolte(bool $jachereRaccourcie): int
    {
        $recoltes = 0;

        for ($quinzaine = 0; $quinzaine < 42; ++$quinzaine) {
            if (CycleAgricoleTerrestre::pourUneQuinzaine($quinzaine, $jachereRaccourcie) > 0) {
                ++$recoltes;
            }
        }

        return $recoltes;
    }

    private function quinzainesDeChantier(string $email, ?PalierDeFaveur $palier): int
    {
        $partie = null === $palier
            ? $this->lancerPartie($email)
            : $this->villeAvec($email, Divinite::Ptah, $palier);

        $ville = $partie->getVille();
        $chantier = new Chantier($ville, TypeDeBatiment::Temple, 5);
        $ville->ajouterChantier($chantier);

        $quinzaines = 0;

        while (!$chantier->estAcheve() && $quinzaines < 40) {
            $chantier->avancerDUnCycle(Saison::Peret, EffetDeFaveur::bonusDeChantier($ville));
            ++$quinzaines;
        }

        return $quinzaines;
    }

    /**
     * Une ville dont un dieu se trouve au palier voulu, obtenue par de vraies
     * offrandes : c'est le seul chemin par lequel une faveur monte, et le
     * test ne doit pas s'en inventer un autre.
     */
    private function villeAvec(string $email, Divinite $divinite, PalierDeFaveur $palier): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000]);

        $offrandes = static::getContainer()->get(Offrandes::class);

        if (PalierDeFaveur::Hostile === $palier) {
            // Aucun chemin ne rend un dieu hostile à ce stade — la négligence
            // s'arrête au neutre. On l'y met à la main, faute de quête ratée.
            $ville->suivreLaFaveurDe($divinite)->ajuster(-Divinite::FAVEUR_DE_DEPART);

            return $partie;
        }

        while (!$palier->estAuDessusDuNeutre() || $ville->palierDe($divinite) !== $palier) {
            $offrandes->offrir($partie, $divinite, Ressource::Deben, 20);

            if ($ville->faveurEnvers($divinite) >= Divinite::FAVEUR_MAXIMALE) {
                break;
            }
        }

        return $partie;
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
