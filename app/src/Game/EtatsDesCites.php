<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\EtatDePartenaire;
use App\Entity\GameSave;
use App\Repository\EtatDePartenaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * L'accès aux états de cité d'une partie (`docs/plan-pnj-palier-a.md` § 4.3).
 *
 * Les états d'une partie se chargent **une fois** par requête : l'étal en lit une dizaine, et une
 * requête par marchandise serait payée pour rien. Le cache se vide à chaque requête
 * (`ResetInterface`), car le mode worker garde les services d'une requête à l'autre.
 *
 * Rien n'est créé en lisant : un état naît à la première marchandise qui rentre.
 */
final class EtatsDesCites implements ResetInterface
{
    /** @var array<int, array<string, EtatDePartenaire>> */
    private array $cache = [];

    public function __construct(
        private readonly EtatDePartenaireRepository $etats,
        private readonly EntityManagerInterface $entityManager,
        private readonly Lignees $lignees,
    ) {
    }

    public function de(GameSave $partie, PartenaireCommercial $partenaire): ?EtatDePartenaire
    {
        return $this->charges($partie)[$partenaire->cite()] ?? null;
    }

    /**
     * L'état de la cité, créé s'il n'existe pas : la relation démarre à l'acquis de la lignée.
     */
    public function obtenir(GameSave $partie, PartenaireCommercial $partenaire): EtatDePartenaire
    {
        $existant = $this->de($partie, $partenaire);

        if (null !== $existant) {
            return $existant;
        }

        $etat = new EtatDePartenaire(
            $partie,
            $partenaire->cite(),
            $this->lignees->pour($partie->getJoueur())->relationAcquise($partenaire->cite()),
        );
        $this->entityManager->persist($etat);
        $this->cache[$this->cleDePartie($partie)][$partenaire->cite()] = $etat;

        return $etat;
    }

    /**
     * @return list<EtatDePartenaire>
     */
    public function tous(GameSave $partie): array
    {
        return array_values($this->charges($partie));
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * @return array<string, EtatDePartenaire>
     */
    private function charges(GameSave $partie): array
    {
        $cle = $this->cleDePartie($partie);

        if (!isset($this->cache[$cle])) {
            $this->cache[$cle] = [];

            foreach ($this->etats->deLaPartie($partie) as $etat) {
                $this->cache[$cle][$etat->getCite()] = $etat;
            }
        }

        return $this->cache[$cle];
    }

    private function cleDePartie(GameSave $partie): int
    {
        return $partie->getId() ?? spl_object_id($partie);
    }
}
