<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Ce qu'un panneau de la fenêtre de la ville a besoin de savoir pour
 * s'afficher — et rien de plus.
 *
 * **Un fournisseur par bâtiment.** `PartieController::ville()` calculait les
 * données de *tous* les panneaux à chaque affichage, alors qu'on n'en ouvre
 * qu'un : la Maison des scribes tirait ses exercices pour une partie qui
 * regardait le Grenier. Chaque panneau calcule désormais le sien, et seulement
 * quand on l'ouvre (`docs/plan-fenetres.md`, phase 6).
 *
 * Un fournisseur est repéré par sa clé — la valeur de `TypeDeBatiment`, ou
 * `essai` — et `PanneauxDeLaVille` va chercher le bon. En ajouter un : créer la
 * classe, l'autoconfiguration fait le reste.
 */
#[AutoconfigureTag('app.panneau_de_ville')]
interface FournisseurDePanneau
{
    /**
     * La clé de l'onglet : `TypeDeBatiment::value`, ou `essai`.
     */
    public static function cle(): string;

    /**
     * @return array<string, mixed>
     */
    public function donnees(GameSave $partie): array;
}
