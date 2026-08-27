<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\GameSave;
use App\Enum\GameMode;
use App\Game\GenerateurDeCarte;
use App\Game\GeographieDeRegion;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dote d'une carte les parties lancées avant que la génération n'existe.
 *
 * Sans elle, ces parties afficheraient un territoire vide à jamais : la carte
 * n'est engendrée qu'au lancement, et le leur est passé.
 */
#[AsCommand(
    name: 'app:parties:generer-cartes-manquantes',
    description: 'Engendre la carte des parties qui n\'en ont pas',
)]
final class GenererCartesManquantesCommand
{
    public function __construct(
        private readonly GameSaveRepository $parties,
        private readonly MissionCatalogue $missions,
        private readonly GenerateurDeCarte $carte,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Liste les parties concernées sans rien engendrer')]
        bool $dryRun = false,
    ): int {
        $sansCarte = array_filter(
            $this->parties->findAll(),
            static fn (GameSave $partie): bool => 0 === $partie->getVille()->getZones()->count(),
        );

        if ([] === $sansCarte) {
            $io->success('Toutes les parties ont leur carte.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Partie', 'Ville', 'Mode'],
            array_map(
                static fn (GameSave $p): array => [(string) $p->getId(), $p->getVille()->getNom(), $p->getMode()->libelle()],
                $sansCarte,
            ),
        );

        if ($dryRun) {
            $io->note(\sprintf('%d partie(s) seraient dotées. Rien n\'a été engendré.', \count($sansCarte)));

            return Command::SUCCESS;
        }

        foreach ($sansCarte as $partie) {
            $this->carte->peupler($partie->getVille(), $this->geographieDe($partie));
        }

        $this->entityManager->flush();
        $io->success(\sprintf('%d carte(s) engendrée(s).', \count($sansCarte)));

        return Command::SUCCESS;
    }

    private function geographieDe(GameSave $partie): GeographieDeRegion
    {
        $mission = $partie->getMission();

        if (GameMode::Campagne === $partie->getMode() && null !== $mission) {
            return $this->missions->get($mission)->geographie;
        }

        return LanceurDePartie::geographieDuModeAventure();
    }
}
