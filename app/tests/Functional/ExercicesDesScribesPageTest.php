<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\ExerciceDesSons;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les exercices des scribes, de la page à la correction : l'écran tire une
 * graine, la renvoie avec les réponses, et le serveur recompose la série.
 */
final class ExercicesDesScribesPageTest extends WebTestCase
{
    public function testLaPageProposeLesDeuxExercicesEtLaSerieSeCorrige(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('exercices-page@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 3));
        $gestionnaire->flush();
        $avant = $ville->getDeben();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=maison_des_scribes', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sons et signes');
        self::assertSelectorTextContains('body', 'Lire un cartouche');
        self::assertCount(1, $crawler->filter('form[action$="/scribes/lecture-cartouche"]'));

        $formulaire = $crawler->filter('form[action$="/scribes/exercice-sons"]');
        self::assertCount(1, $formulaire);

        $graine = (int) $formulaire->filter('input[name="graine"]')->attr('value');
        $jeton = (string) $formulaire->filter('input[name="_token"]')->attr('value');
        $bonnes = array_column(ExerciceDesSons::serie($partie->getVille(), $graine), 'bonne');

        $client->request('POST', \sprintf('/partie/%d/scribes/exercice-sons', $partie->getId()), [
            '_token' => $jeton,
            'onglet' => 'maison_des_scribes',
            'graine' => $graine,
            'reponse' => $bonnes,
        ]);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', '6 bonnes réponses sur 6');

        $gestionnaire->clear();
        $rechargee = $gestionnaire->find(\App\Entity\GameSave::class, $partie->getId());
        self::assertSame($avant + ExerciceDesSons::RECOMPENSE_EN_DEBEN, $rechargee?->getVille()->getDeben());
    }
}
