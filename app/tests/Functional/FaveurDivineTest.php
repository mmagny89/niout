<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\PalierDeFaveur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le panthéon et l'échelle de faveur (lot 6.0).
 *
 * Rien n'agit encore : ce lot pose la mesure, pas ses effets. Ce qui se
 * vérifie ici est donc surtout de la tenue — des bornes, une honnêteté
 * d'affichage, et le fait qu'une ville qui n'a jamais offert ne traîne aucune
 * ligne en base.
 */
final class FaveurDivineTest extends KernelTestCase
{
    /**
     * **Ne pas honorer un dieu ne coûte rien.** C'est l'écart tranché avec le
     * doc 07, qui annonce un départ « neutre à 50 » tout en plaçant le palier
     * Favorable à partir de 50 : suivi à la lettre, il offrirait huit bonus
     * actifs à qui n'a jamais mis les pieds au Temple.
     */
    public function testUneVilleNeuveEstNeutreEnversTousLesDieux(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('pantheon@example.com')->getVille();

        foreach (Divinite::pantheon() as $divinite) {
            self::assertSame(Divinite::FAVEUR_DE_DEPART, $ville->faveurEnvers($divinite));
            self::assertSame(
                PalierDeFaveur::Neutre,
                $ville->palierDe($divinite),
                \sprintf('%s ne doit ni favoriser ni punir une ville qui l\'ignore.', $divinite->libelle()),
            );
        }

        self::assertSame([], $ville->divinitesHonorees());
        self::assertCount(0, $ville->getFaveurs(), 'Une ville qui n\'a rien offert ne traîne aucune ligne.');
    }

    /**
     * Une ligne naît au premier geste, et à celui-là seulement : c'est ce qui
     * évite d'écrire huit fois la même constante au lancement de chaque
     * partie, et de les migrer à chaque divinité ajoutée.
     */
    public function testUneLigneNaitAuPremierGesteEtPasAvant(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('premiere-offrande@example.com');
        $ville = $partie->getVille();

        $faveur = $ville->suivreLaFaveurDe(Divinite::Ptah);
        $faveur->recevoirUneOffrande(15);

        self::assertCount(1, $ville->getFaveurs());
        self::assertSame($faveur, $ville->suivreLaFaveurDe(Divinite::Ptah), 'Le second appel ne crée pas de doublon.');
        self::assertSame(Divinite::FAVEUR_DE_DEPART + 15, $ville->faveurEnvers(Divinite::Ptah));
        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Ptah));
        self::assertSame([Divinite::Ptah], $ville->divinitesHonorees());

        $this->gestionnaire()->flush();
        $this->gestionnaire()->clear();

        $rechargee = $this->gestionnaire()->find(GameSave::class, $partie->getId());
        self::assertNotNull($rechargee);
        self::assertSame(
            Divinite::FAVEUR_DE_DEPART + 15,
            $rechargee->getVille()->faveurEnvers(Divinite::Ptah),
            'La faveur survit à un aller-retour en base.',
        );
    }

    /**
     * Les bornes tiennent **dans l'entité**, pas dans chacun de ses appelants :
     * offrande, fête, bénédiction et malédiction passeront tous par `ajuster()`,
     * et aucun n'a à vérifier l'échelle pour son compte.
     */
    public function testLaFaveurNeSortJamaisDeSonEchelle(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('bornes@example.com')->getVille();
        $faveur = $ville->suivreLaFaveurDe(Divinite::Hapi);

        $faveur->ajuster(10_000);
        self::assertSame(Divinite::FAVEUR_MAXIMALE, $faveur->getFaveur());
        self::assertSame(PalierDeFaveur::Devoue, $faveur->getPalier());

        $faveur->ajuster(-10_000);
        self::assertSame(Divinite::FAVEUR_MINIMALE, $faveur->getFaveur());
        self::assertSame(PalierDeFaveur::Hostile, $faveur->getPalier());
        self::assertTrue($faveur->getPalier()->nuit());
    }

    /**
     * Une offrande remet le compteur de négligence à zéro — c'est ce qui
     * distinguera, au lot 6.2, un dieu qu'on entretient d'un dieu qu'on
     * laisse filer.
     */
    public function testUneOffrandeInterrompLaNegligence(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('negligence@example.com')->getVille();
        $faveur = $ville->suivreLaFaveurDe(Divinite::Sekhmet);

        $faveur->attendreUneQuinzaine()->attendreUneQuinzaine()->attendreUneQuinzaine();
        self::assertSame(3, $faveur->getQuinzainesSansOffrande());

        $faveur->recevoirUneOffrande(5);
        self::assertSame(0, $faveur->getQuinzainesSansOffrande());
    }

    /**
     * **Un dieu sans emploi le dit.** Promettre un effet qui ne s'applique
     * nulle part tromperait le joueur au moment même où il choisit à qui
     * donner — la règle des spécialités de chef, appliquée au panthéon.
     */
    public function testUnDieuSansSystemeDaccueilLannonce(): void
    {
        foreach (Divinite::pantheon() as $divinite) {
            self::assertNotSame('', $divinite->libelle());
            self::assertNotSame('', $divinite->domaine());
            self::assertNotSame('', $divinite->effet());

            if ($divinite->agitDeja()) {
                self::assertNull($divinite->attente(), \sprintf('%s agit : rien à faire attendre.', $divinite->libelle()));

                continue;
            }

            self::assertNotNull(
                $divinite->attente(),
                \sprintf('%s ne fait rien encore, et doit l\'annoncer.', $divinite->libelle()),
            );
        }

        self::assertFalse(Divinite::Isis->agitDeja(), 'Isis attend le combat.');
        // Thot a cessé d'attendre au lot 7.7 : il éclaire les écrits, et
        // abrège la reprise d'un dossier mal conclu.
        self::assertTrue(Divinite::Thot->agitDeja());
    }

    /**
     * Les plages du doc 07, verrouillées à leurs frontières — c'est là que ce
     * genre de table se trompe d'un point.
     */
    public function testLesFrontieresDesPaliers(): void
    {
        self::assertSame(PalierDeFaveur::Hostile, PalierDeFaveur::pour(0));
        self::assertSame(PalierDeFaveur::Hostile, PalierDeFaveur::pour(24));
        self::assertSame(PalierDeFaveur::Neutre, PalierDeFaveur::pour(25));
        self::assertSame(PalierDeFaveur::Neutre, PalierDeFaveur::pour(49));
        self::assertSame(PalierDeFaveur::Favorable, PalierDeFaveur::pour(50));
        self::assertSame(PalierDeFaveur::Favorable, PalierDeFaveur::pour(79));
        self::assertSame(PalierDeFaveur::Devoue, PalierDeFaveur::pour(80));
        self::assertSame(PalierDeFaveur::Devoue, PalierDeFaveur::pour(100));

        self::assertFalse(PalierDeFaveur::Neutre->estAuDessusDuNeutre());
        self::assertFalse(PalierDeFaveur::Neutre->nuit());
        self::assertTrue(PalierDeFaveur::Devoue->estAuDessusDuNeutre());
    }

    /**
     * Les faveurs appartiennent à leur partie : abandonner une partie les
     * emporte, comme tout le reste de la ville.
     */
    public function testLesFaveursSuiventLaPartieQuiDisparait(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('cascade@example.com');
        $partie->getVille()->suivreLaFaveurDe(Divinite::Osiris)->recevoirUneOffrande(5);

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->flush();

        $gestionnaire->remove($partie);
        $gestionnaire->flush();

        self::assertSame(
            0,
            (int) $gestionnaire->getConnection()->fetchOne('SELECT COUNT(*) FROM faveur_divine'),
        );
    }

    private function lancerPartie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
