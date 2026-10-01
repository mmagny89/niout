<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AlphabetDesScribes;
use App\Game\CartoucheRoyal;
use App\Game\ExerciceDesSons;
use App\Game\LanceurDePartie;
use App\Game\LectureDeCartouche;
use App\Game\Ressource;
use App\Game\SigneDeCartouche;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Pratiquer les sons, lire un cartouche : deux exercices qui se déduisent
 * d'une graine et ne récompensent qu'une fois par quinzaine.
 */
final class ExercicesDesScribesTest extends KernelTestCase
{
    public function testLaSerieNePorteQueSurLesSignesConnusEtSeRejoue(): void
    {
        self::bootKernel();
        $partie = $this->partie('serie@example.com', niveau: 2);
        $ville = $partie->getVille();
        $connus = array_map(static fn ($s): string => $s->value, AlphabetDesScribes::pour($ville));

        $serie = ExerciceDesSons::serie($ville, 1234);

        self::assertCount(ExerciceDesSons::QUESTIONS, $serie);
        self::assertSame($serie, ExerciceDesSons::serie($ville, 1234), 'La même graine rend la même série.');
        self::assertNotSame($serie, ExerciceDesSons::serie($ville, 99), 'Une autre graine, une autre série.');

        foreach ($serie as $question) {
            $valeurs = array_column($question['options'], 'valeur');

            self::assertContains($question['bonne'], $valeurs, 'La bonne réponse figure parmi les propositions.');
            self::assertSame($valeurs, array_values(array_unique($valeurs)), 'Pas deux fois la même proposition.');
            self::assertLessThanOrEqual(ExerciceDesSons::PROPOSITIONS, \count($valeurs));
            self::assertEmpty(array_diff($valeurs, $connus), 'On n\'interroge pas sur un signe encore inconnu.');
        }
    }

    public function testToutJusteRecompenseUneFoisParQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->partie('sons-juste@example.com');
        $ville = $partie->getVille();
        $exercice = static::getContainer()->get(ExerciceDesSons::class);
        $bonnes = array_column(ExerciceDesSons::serie($ville, 42), 'bonne');
        $avant = $ville->getDeben();

        $premier = $exercice->repondre($partie, 42, $bonnes);
        self::assertTrue($premier['reussie']);
        self::assertSame(ExerciceDesSons::RECOMPENSE_EN_DEBEN, $premier['recompense']);
        self::assertSame($avant + ExerciceDesSons::RECOMPENSE_EN_DEBEN, $ville->getDeben());

        $second = $exercice->repondre($partie, 42, $bonnes);
        self::assertTrue($second['reussie']);
        self::assertSame(0, $second['recompense'], 'Une seule récompense par quinzaine.');
        self::assertSame($avant + ExerciceDesSons::RECOMPENSE_EN_DEBEN, $ville->getDeben());

        $partie->avancerDUnCycle();
        $troisieme = $exercice->repondre($partie, 42, $bonnes);
        self::assertSame(ExerciceDesSons::RECOMPENSE_EN_DEBEN, $troisieme['recompense'], 'Elle revient à la quinzaine suivante.');
    }

    public function testUneSerieRateeNeRapporteRienEtDitCeQuilFallaitSavoir(): void
    {
        self::bootKernel();
        $partie = $this->partie('sons-rate@example.com');
        $ville = $partie->getVille();
        $avant = $ville->getDeben();

        $bilan = static::getContainer()->get(ExerciceDesSons::class)->repondre($partie, 7, []);

        self::assertFalse($bilan['reussie']);
        self::assertSame(0, $bilan['bonnes']);
        self::assertSame(0, $bilan['recompense']);
        self::assertSame($avant, $ville->getDeben());
        self::assertCount(ExerciceDesSons::QUESTIONS, $bilan['corrections']);
    }

    public function testChaqueCartoucheProposeNePorteQueDesSignesConnus(): void
    {
        $eligibles = LectureDeCartouche::eligibles();

        self::assertNotSame([], $eligibles);

        foreach ($eligibles as $cartouche) {
            foreach ($cartouche->codesDeGardiner() as $code) {
                $signe = SigneDeCartouche::tryFrom($code);
                self::assertNotNull($signe, $cartouche->lecture().' porte '.$code.', inconnu.');

                // La donnée est confrontée au cartouche : la lecture d'un signe
                // doit figurer dans la translittération du nom entier.
                self::assertStringContainsString(
                    $signe->translitteration(),
                    $cartouche->translitteration(),
                    \sprintf('%s : %s ne se lit pas « %s » dans ce nom.', $cartouche->lecture(), $code, $signe->translitteration()),
                );
            }
        }
    }

    public function testChaqueSigneDeCartoucheServAuMoinsUnCartouche(): void
    {
        $utilises = [];

        foreach (CartoucheRoyal::cases() as $cartouche) {
            foreach ($cartouche->codesDeGardiner() as $code) {
                $utilises[$code] = true;
            }
        }

        foreach (SigneDeCartouche::cases() as $signe) {
            self::assertArrayHasKey($signe->value, $utilises, $signe->value.' ne figure dans aucun cartouche.');
        }
    }

    public function testLExerciceDeLectureSeRejoueEtOffreLesBonnesLectures(): void
    {
        $exercice = LectureDeCartouche::exercice(555);

        self::assertSame($exercice, LectureDeCartouche::exercice(555));

        $valeurs = array_column($exercice['options'], 'valeur');

        foreach ($exercice['signes'] as $signe) {
            $attendu = SigneDeCartouche::from($signe['code']);
            $lectures = array_map(static fn (string $v): string => SigneDeCartouche::from($v)->translitteration(), $valeurs);

            self::assertContains($attendu->translitteration(), $lectures, $signe['code'].' doit pouvoir se choisir.');
            self::assertNotSame('', $signe['glyphe']);
        }
    }

    public function testLireToutJusteRecompenseUneFoisEtDonneLaLecon(): void
    {
        self::bootKernel();
        $partie = $this->partie('lecture-juste@example.com');
        $ville = $partie->getVille();
        $lecture = static::getContainer()->get(LectureDeCartouche::class);
        $exercice = LectureDeCartouche::exercice(31);

        $reponses = [];
        foreach ($exercice['signes'] as $signe) {
            $reponses[$signe['code']] = $signe['code'];
        }

        $avant = $ville->getDeben();
        $bilan = $lecture->repondre($partie, 31, $reponses);

        self::assertTrue($bilan['juste']);
        self::assertSame(LectureDeCartouche::RECOMPENSE_EN_DEBEN, $bilan['recompense']);
        self::assertSame($avant + LectureDeCartouche::RECOMPENSE_EN_DEBEN, $ville->getDeben());
        self::assertStringContainsString($exercice['cartouche']->lecture(), $bilan['lecon']);
        self::assertStringContainsString('à la fin', $bilan['lecon'], 'Le disque solaire se prononce à la fin.');

        self::assertSame(0, $lecture->repondre($partie, 31, $reponses)['recompense'], 'Une fois par quinzaine.');
    }

    public function testUneLectureFausseNeRapporteRien(): void
    {
        self::bootKernel();
        $partie = $this->partie('lecture-fausse@example.com');
        $avant = $partie->getVille()->getDeben();

        $bilan = static::getContainer()->get(LectureDeCartouche::class)->repondre($partie, 31, []);

        self::assertFalse($bilan['juste']);
        self::assertSame(0, $bilan['recompense']);
        self::assertSame($avant, $partie->getVille()->getDeben());
        self::assertNotSame([], $bilan['details'], 'La correction dit ce que fait chaque signe.');
    }

    private function partie(string $email, int $niveau = 3): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, $niveau));
        $ville->crediterRessources([Ressource::Deben->value => 100]);
        $gestionnaire->flush();

        return $partie;
    }
}
