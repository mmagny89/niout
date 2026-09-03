<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Game\CartoucheRoyal;
use App\Game\ChantierRoyal;
use App\Game\SuccessionDesRegnes;
use PHPUnit\Framework\TestCase;

/**
 * Le contenu royal qui se renouvelle (doc 14, lot 11.3).
 *
 * **C'est l'argument pédagogique du document** : une partie Aventure fait
 * rencontrer bien plus de pharaons qu'une campagne de dix missions. Encore
 * faut-il que chacun apporte quelque chose — son cartouche et son chantier —,
 * sans quoi la succession ne serait qu'un défilé de noms.
 */
final class ContenuRoyalDeMemphisTest extends TestCase
{
    /**
     * **Les treize règnes ont désormais leur cartouche.** Sept manquaient à la
     * livraison du lot 11.1 : la campagne n'en commandite aucun, et le jeu
     * n'affichait donc rien pour eux — ce qui était juste, mais pauvre.
     */
    public function testChaqueRegneAsonCartouche(): void
    {
        foreach ((new SuccessionDesRegnes())->tous() as $regne) {
            $cartouche = $regne->cartouche();

            self::assertInstanceOf(
                CartoucheRoyal::class,
                $cartouche,
                \sprintf('%s n\'a pas de cartouche.', $regne->pharaon),
            );

            // Le nom de trône **se lit sur le cartouche**, il n'est pas
            // recopié à côté : les deux avaient divergé dès qu'on les avait
            // écrits deux fois.
            self::assertSame($cartouche->lecture(), $regne->nomDeTrone());
        }
    }

    /**
     * **Chaque règne réclame quelque chose.** Un pharaon qui ne demanderait
     * rien serait un règne muet, et le renouvellement du contenu est
     * précisément ce que le doc 14 attend de la succession.
     *
     * Les monuments sont attestés, et choisis dans le règne qui les a vus :
     * `CodesDeGardinerTest` garantit les signes, ce test garantit qu'il y en a.
     */
    public function testChaqueRegneReclameUnChantier(): void
    {
        foreach ((new SuccessionDesRegnes())->tous() as $regne) {
            $chantier = ChantierRoyal::pour($regne->pharaon);

            self::assertInstanceOf(
                ChantierRoyal::class,
                $chantier,
                \sprintf('%s ne réclame rien.', $regne->pharaon),
            );
            self::assertNotSame('', $chantier->libelle());
            self::assertNotSame('', $chantier->ceQuOnEnSait());
        }
    }

    /**
     * Deux règnes ne bâtissent pas le même monument : le contenu se renouvelle
     * vraiment, il ne se répète pas sous un autre nom.
     */
    public function testDeuxRegnesNeReclamentPasLeMemeChantier(): void
    {
        $chantiers = [];

        foreach ((new SuccessionDesRegnes())->tous() as $regne) {
            $chantier = ChantierRoyal::pour($regne->pharaon);
            self::assertNotNull($chantier);

            self::assertNotContains(
                $chantier,
                $chantiers,
                \sprintf('%s réclame un chantier déjà réclamé.', $regne->pharaon),
            );

            $chantiers[] = $chantier;
        }
    }
}
