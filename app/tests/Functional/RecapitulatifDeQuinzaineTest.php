<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Game\CategorieDEvenement;
use App\Game\LanceurDePartie;
use App\Game\RecapitulatifDeQuinzaine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le récapitulatif de la quinzaine : ce qui a changé, puis le journal rangé par
 * catégorie, à la place d'une pile de messages d'égale importance.
 */
final class RecapitulatifDeQuinzaineTest extends WebTestCase
{
    public function testPasserUneQuinzaineAffichePuisConsommeLeRecapitulatif(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'recap@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $formulaire = $crawler->filter(\sprintf('form[action="/partie/%d/cycle"]', $partie->getId()));
        $client->request('POST', \sprintf('/partie/%d/cycle', $partie->getId()), [
            '_token' => $formulaire->filter('input[name="_token"]')->attr('value'),
            'retour' => 'app_partie_carte',
        ]);
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $recap = $crawler->filter('section[aria-label="Récapitulatif de la quinzaine"]');
        self::assertCount(1, $recap, 'Le récapitulatif s\'affiche après la quinzaine.');
        self::assertStringContainsString('Nous voici en', $recap->text());
        self::assertCount(1, $recap->filter('[data-action="flash#fermer"]'), 'Il se ferme au geste, comme tout message.');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        self::assertCount(0, $crawler->filter('section[aria-label="Récapitulatif de la quinzaine"]'), 'Un message flash ne se lit qu\'une fois.');
    }

    public function testLesEcartsViennentDeLEtatEtNonDuTexteDuJournal(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'recap-ecarts@example.com');
        $service = static::getContainer()->get(RecapitulatifDeQuinzaine::class);

        $avant = $service->photographier($partie);
        $partie->getVille()->crediterRessources([\App\Game\Ressource::Deben->value => 40]);

        $recap = $service->composer($avant, $partie, [
            ['categorie' => CategorieDEvenement::Dieux, 'texte' => 'Isis sourit.'],
            ['categorie' => CategorieDEvenement::Sante, 'texte' => 'La fièvre recule.'],
            ['categorie' => CategorieDEvenement::Sante, 'texte' => 'Un lit se libère.'],
        ]);

        self::assertCount(1, $recap['ecarts'], 'Seul ce qui a bougé figure.');
        self::assertSame(40, $recap['ecarts'][0]['ecart']);
        self::assertSame('deben', $recap['ecarts'][0]['icone']);
        // Dans l'ordre des catégories, pas dans celui des événements ; les lignes d'une
        // même catégorie restent groupées dans leur ordre.
        self::assertSame(['Santé', 'Les dieux'], array_column($recap['groupes'], 'libelle'));
        self::assertSame(['La fièvre recule.', 'Un lit se libère.'], $recap['groupes'][0]['textes']);
    }

    public function testChaqueCategorieADesPictogrammeQuiExiste(): void
    {
        $racine = \dirname(__DIR__, 2).'/assets/images/interface/';
        foreach (CategorieDEvenement::cases() as $categorie) {
            self::assertFileExists($racine.$categorie->icone().'.webp', $categorie->value);
        }
    }

    private function partie(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): \App\Entity\GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
