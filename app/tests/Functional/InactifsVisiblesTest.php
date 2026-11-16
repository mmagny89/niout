<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Fenetre\FigureDeLaVille;
use App\Fenetre\GensDeLaVille;
use App\Game\LanceurDePartie;
use App\Game\PhotoDeLaVille;
use App\Game\RoleDeFigure;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les enfants et les anciens se voient dans la ville, et se cliquent comme le reste.
 */
final class InactifsVisiblesTest extends WebTestCase
{
    public function testLaPhotographieMontreLesEnfantsEtLesAnciensDeLaVille(): void
    {
        static::createClient();
        $partie = $this->lancer('inactifs@example.com');
        $ville = $partie->getVille();

        self::assertGreaterThan(0, $ville->getEnfants());
        self::assertGreaterThan(0, $ville->getAnciens());

        $photo = static::getContainer()->get(PhotoDeLaVille::class)->prendre($partie);
        $roles = array_count_values(array_column($photo['candidats'], 'role'));

        self::assertGreaterThan(0, $roles[RoleDeFigure::Enfant->value] ?? 0);
        self::assertGreaterThan(0, $roles[RoleDeFigure::Ancien->value] ?? 0);
        self::assertLessThanOrEqual($ville->getEnfants(), $roles[RoleDeFigure::Enfant->value], 'Jamais plus d\'enfants que la ville n\'en compte.');
        self::assertLessThanOrEqual($ville->getAnciens(), $roles[RoleDeFigure::Ancien->value]);
    }

    public function testLaVilleLesPoseEtLaFicheLesDitRepresentatifs(): void
    {
        $client = static::createClient();
        $partie = $this->lancer('inactifs-fiche@example.com');
        $client->loginUser($partie->getJoueur());
        // Le Quartier dressé : sans lui, tout le monde se rabat sur la Résidence, qui n'a que huit places.
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, 2));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $figures = static::getContainer()->get(GensDeLaVille::class)->pour($partie)['figures'];
        $enfants = array_values(array_filter($figures, static fn (FigureDeLaVille $f): bool => RoleDeFigure::Enfant === $f->role));
        $anciens = array_values(array_filter($figures, static fn (FigureDeLaVille $f): bool => RoleDeFigure::Ancien === $f->role));

        self::assertNotSame([], $enfants);
        self::assertNotSame([], $anciens);

        foreach ([$enfants[0], $anciens[0]] as $figure) {
            $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=%s', $partie->getId(), $figure->cle), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Représentatif');
        }

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=enfant-99', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseStatusCodeSame(404);
    }

    private function lancer(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }
}
