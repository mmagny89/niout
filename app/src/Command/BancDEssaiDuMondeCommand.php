<?php

declare(strict_types=1);

namespace App\Command;

use App\Game\AvantageDeNegoce;
use App\Game\BancDEssaiDuMonde;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fait tourner le monde sans joueur et vérifie ses bornes (`docs/plan-pnj-palier-a.md` § 10).
 * À rejouer après tout changement de `ConjonctureDeMarche`, et avant de recalibrer.
 */
#[AsCommand(
    name: 'app:monde:banc-d-essai',
    description: 'Fait tourner la conjoncture des marchés sans joueur et vérifie ses bornes',
)]
final class BancDEssaiDuMondeCommand
{
    public function __construct(private readonly BancDEssaiDuMonde $banc)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nombre de quinzaines à faire tourner')]
        int $quinzaines = 200,
        #[Option(description: 'Nombre de graines différentes')]
        int $graines = 5,
    ): int {
        $rapport = $this->banc->mesurer(
            array_map(static fn (int $i): int => 1 + $i * 7919, range(0, max(1, $graines) - 1)),
            $quinzaines,
        );

        $io->definitionList(
            ['Partenaires' => (string) \count($this->banc->partenaires())],
            ['Mesures' => (string) $rapport['mesures']],
            ['Conjoncture' => \sprintf('de %d à %d', $rapport['minimum'], $rapport['maximum'])],
            ['Mesures non nulles' => \sprintf('%d %%', (int) round(100 * $rapport['nonNulles'] / max(1, $rapport['mesures'])))],
            ['Avantage total' => \sprintf('de %d à %d (plafond %d)', $rapport['avantageMinimal'], $rapport['avantageMaximal'], AvantageDeNegoce::PLAFOND_TOTAL)],
            ['Plancher d\'achat observé' => \sprintf('%d %% du cours (cours bon marché : arrondi à l\'entier ; 110 %% exigé, vérifié exactement)', $rapport['plancherDAchat'])],
        );

        ksort($rapport['evenements']);
        $io->table(['Événement', 'Mesures'], array_map(
            static fn (string $nom, int $n): array => [$nom, $n],
            array_keys($rapport['evenements']),
            $rapport['evenements'],
        ));

        if ([] !== $rapport['violations'] || $rapport['avantageMaximal'] > AvantageDeNegoce::PLAFOND_TOTAL) {
            $io->error([] === $rapport['violations'] ? 'Une borne est rompue.' : \array_slice($rapport['violations'], 0, 10));

            return Command::FAILURE;
        }

        $io->success('Toutes les bornes tiennent.');

        return Command::SUCCESS;
    }
}
