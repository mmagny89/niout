<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\MessagesDeJeuExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagesDeJeuExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?string, string, string}>
     */
    public static function messages(): iterable
    {
        yield 'deux phrases' => [
            'L\'éclaireur part en reconnaissance vers la case (2, 1). Il sera sur place dans 2 cycles.',
            'L\'éclaireur part en reconnaissance vers la case (2, 1)', 'Il sera sur place dans 2 cycles.', '. ', 'expedition',
        ];
        yield 'titre : détail' => [
            'Une maisonnée s\'installe : 2 bras et 1 bouches de plus.',
            'Une maisonnée s\'installe', '2 bras et 1 bouches de plus.', ' : ', 'habitants',
        ];
        yield 'une seule phrase' => ['Marché conclu.', 'Marché conclu.', null, '', 'echange'];
        yield 'sans motif connu' => ['Les réserves sont de nouveau pleines.', 'Les réserves sont de nouveau pleines.', null, '', 'amelioration'];
    }

    #[DataProvider('messages')]
    public function testUnMessageSeRangeEnCarteSansPerdreUnMot(string $message, string $titre, ?string $detail, string $liaison, string $icone): void
    {
        $carte = (new MessagesDeJeuExtension())->presenter($message);

        self::assertSame($titre, $carte['titre']);
        self::assertSame($detail, $carte['detail']);
        self::assertSame($icone, $carte['icone']);
        // Titre + liaison + détail redonnent le message d'origine : rien n'est réécrit.
        self::assertSame($message, $carte['titre'].$carte['liaison'].($carte['detail'] ?? ''));
    }

    public function testUneErreurPrendLePictogrammeDeDanger(): void
    {
        self::assertSame('danger', (new MessagesDeJeuExtension())->presenter('Il vous faut une Forge pour cela.', 'erreur')['icone']);
    }
}
