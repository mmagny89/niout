<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Family;
use App\Game\Enigme;
use App\Game\Enquete;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Ce que les affaires de l'esprit rapportent en renommée (doc 13, lot 9.2).
 *
 * Le document accorde de la renommée par énigme et par enquête résolue ; le jeu
 * ne donnait rien pour la première et un point pour la seconde. Le plafond de
 * mission est la moitié du lot : sans lui, deux mini-jeux rempliraient à eux
 * seuls une jauge qui se lit sur cent points et prétend mesurer une réputation.
 */
final class RenommeeDesAffairesTest extends WebTestCase
{
    /**
     * Le doc 13 accorde un point par énigme résolue. C'était une règle écrite
     * et jamais appliquée — le même piège qu'`ajusterRenommee()` en son temps.
     */
    public function testUneEnigmeResolueRapporteUnPoint(): void
    {
        $famille = new Family('Nakht');

        self::assertSame(
            Enigme::RENOMMEE_POUR_UNE_RESOLUE,
            $famille->crediterUneAffaireResolue(Enigme::RENOMMEE_POUR_UNE_RESOLUE),
        );
        self::assertSame(1, $famille->getRenommee());
    }

    /**
     * Une enquête vaut plus qu'une énigme, et c'est le point du lot : elle
     * demande plusieurs quinzaines de collecte là où une énigme se répond en
     * une fois.
     */
    public function testUneEnqueteVautPlusQuUneEnigme(): void
    {
        self::assertGreaterThan(Enigme::RENOMMEE_POUR_UNE_RESOLUE, Enquete::RENOMMEE_POUR_UNE_RESOLUE);
    }

    /**
     * **Le plafond de la mission tient.** Une fois atteint, une affaire de plus
     * ne rapporte rien — et le zéro rendu est ce qui permet à l'écran de se
     * taire plutôt que d'annoncer un gain nul.
     */
    public function testLePlafondDeLaMissionTient(): void
    {
        $famille = new Family('Nakht');
        $verse = 0;

        // Bien plus d'affaires que le plafond n'en couvre.
        for ($i = 0; $i < 20; ++$i) {
            $verse += $famille->crediterUneAffaireResolue(Enquete::RENOMMEE_POUR_UNE_RESOLUE);
        }

        self::assertSame(Family::RENOMMEE_MAX_DES_AFFAIRES, $verse);
        self::assertSame(Family::RENOMMEE_MAX_DES_AFFAIRES, $famille->getRenommee());
        self::assertSame(0, $famille->crediterUneAffaireResolue(Enquete::RENOMMEE_POUR_UNE_RESOLUE));
    }

    /**
     * **Le plafond ne borne que les affaires**, jamais la jauge : elle bouge
     * pour six raisons, et un plafond qui la lirait plafonnerait les cinq
     * autres. Une famille au plafond des affaires gagne encore par le Marché
     * ou par une quête de chantier.
     */
    public function testLePlafondNeBornePasLesAutresSources(): void
    {
        $famille = new Family('Nakht');

        for ($i = 0; $i < 20; ++$i) {
            $famille->crediterUneAffaireResolue(Enquete::RENOMMEE_POUR_UNE_RESOLUE);
        }

        $famille->ajusterRenommee(30);

        self::assertSame(Family::RENOMMEE_MAX_DES_AFFAIRES + 30, $famille->getRenommee());
    }

    /**
     * Le plafond se compte **par mission** : une nouvelle partie repart avec
     * son propre quota, sinon une campagne entière n'accorderait que huit
     * points à deux systèmes qui la traversent de bout en bout.
     */
    public function testChaqueMissionRetrouveSonQuota(): void
    {
        $premiere = new Family('Nakht');

        for ($i = 0; $i < 20; ++$i) {
            $premiere->crediterUneAffaireResolue(Enquete::RENOMMEE_POUR_UNE_RESOLUE);
        }

        // La mission suivante démarre à l'acquis de la lignée, quota neuf.
        $seconde = new Family('Nakht', $premiere->getRenommee());

        self::assertSame(0, $seconde->getRenommeeDesAffaires());
        self::assertSame(
            Enquete::RENOMMEE_POUR_UNE_RESOLUE,
            $seconde->crediterUneAffaireResolue(Enquete::RENOMMEE_POUR_UNE_RESOLUE),
        );
    }
}
