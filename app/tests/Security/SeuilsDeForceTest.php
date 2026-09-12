<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\PasswordStrengthValidator;

/**
 * L'écran de saisie annonce la force d'un mot de passe **avant** que le
 * serveur ne se prononce, et il le fait en JavaScript — donc en reproduisant
 * l'estimation de Symfony, dans
 * `assets/controllers/force_du_mot_de_passe_controller.js`.
 *
 * Deux implémentations d'une même formule finissent toujours par diverger, et
 * cette divergence-là est silencieuse : l'écran annoncerait « fort » là où le
 * serveur refuse, ou l'inverse. Un test fonctionnel n'y peut rien, le client
 * de test n'exécutant pas de JavaScript.
 *
 * La parade est de figer ici le comportement de Symfony sur des mots de passe
 * témoins, un par palier. Si une montée de version le change, ce test échoue —
 * et son échec dit qu'il faut reprendre la reproduction en JavaScript, pas
 * seulement mettre à jour les valeurs attendues.
 */
final class SeuilsDeForceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function motsDePasseTemoins(): iterable
    {
        // Les paliers : 0 très faible, 1 faible, 2 moyen (le minimum exigé),
        // 3 fort, 4 très fort. Les seuils d'entropie sont 60, 80, 100 et 120.
        yield 'vide' => ['', 0];
        yield 'court' => ['niout', 0];
        yield 'douze fois la même lettre' => ['aaaaaaaaaaaa', 0];
        yield 'douze minuscules' => ['grenierflama', 0];
        yield 'quinze, avec un chiffre' => ['grenierflamant1', 1];
        yield 'vingt minuscules' => ['grenierflamantcuivre', 2];
        yield 'trois mots séparés' => ['grenier-flamant-cuivre', 3];
        yield 'quatre mots séparés' => ['grenier-flamant-cuivre-orage', 4];
    }

    /**
     * Le conseil affiché à l'écran n'est pas une opinion, il se mesure.
     *
     * « Malkata2026! » coche tous les réflexes appris — douze caractères, une
     * majuscule, des chiffres, un symbole — et n'atteint pourtant pas le
     * minimum exigé. Vingt minuscules sans le moindre artifice le dépassent.
     * C'est ce que l'écran conseille, et c'est ce que le serveur mesure.
     */
    public function testQuatreMotsValentMieuxQuUnMotTruffeDeSymboles(): void
    {
        $reflexeAppris = PasswordStrengthValidator::estimateStrength('Malkata2026!');
        $quatreMots = PasswordStrengthValidator::estimateStrength('grenier-flamant-cuivre-orage');

        self::assertSame(1, $reflexeAppris, 'En dessous du minimum exigé, qui est 2.');
        self::assertSame(4, $quatreMots);
        self::assertGreaterThan($reflexeAppris, $quatreMots);
    }

    #[DataProvider('motsDePasseTemoins')]
    public function testSymfonyEstimeLaForceCommeLeFaitLEcran(string $motDePasse, int $forceAttendue): void
    {
        self::assertSame(
            $forceAttendue,
            PasswordStrengthValidator::estimateStrength($motDePasse),
            'Si ce palier a changé, la reproduction JavaScript de '
            .'force_du_mot_de_passe_controller.js est à reprendre en même temps.',
        );
    }

    /**
     * L'estimation compte des **octets**, pas des caractères : `strlen` et
     * `count_chars` de PHP travaillent sur des octets, et un « é » en pèse
     * deux. La reproduction JavaScript passe donc par TextEncoder ; sans lui,
     * les deux mesures divergeraient dès le premier accent.
     */
    public function testLEstimationCompteDesOctetsEtNonDesCaracteres(): void
    {
        $avecAccents = 'crèmebrûléeàgogo';

        self::assertSame(16, mb_strlen($avecAccents), 'Seize caractères…');
        self::assertSame(20, \strlen($avecAccents), '…mais vingt octets.');

        // Les octets de continuation UTF-8 valent 128 ou plus, ce qui ouvre le
        // répertoire « autre » — 128 signes — et fait bondir l'entropie.
        self::assertSame(4, PasswordStrengthValidator::estimateStrength($avecAccents));
    }
}
