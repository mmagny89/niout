<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Le fournisseur du panneau ouvert — et lui seul.
 */
final readonly class PanneauxDeLaVille
{
    public function __construct(
        #[AutowireLocator('app.panneau_de_ville', defaultIndexMethod: 'cle')]
        private ContainerInterface $fournisseurs,
    ) {
    }

    /**
     * Les données du panneau `$cle`. Un onglet sans fournisseur n'a besoin de
     * rien de plus que ce que la fenêtre fournit à tous.
     *
     * @return array<string, mixed>
     */
    public function pour(string $cle, GameSave $partie): array
    {
        if (!$this->fournisseurs->has($cle)) {
            return [];
        }

        $fournisseur = $this->fournisseurs->get($cle);
        \assert($fournisseur instanceof FournisseurDePanneau);

        return $fournisseur->donnees($partie);
    }
}
