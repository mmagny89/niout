<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\GameSave;
use App\Fenetre\ResumeDeMission;
use Twig\Attribute\AsTwigFunction;

/**
 * `resume_de_mission(partie)` : le résumé de la mission du pharaon, pour l'en-tête. La barre se rend à trois
 * endroits (la coque, la barre rechargée, la fenêtre) : une fonction plutôt qu'une variable à passer partout.
 */
final readonly class MissionExtension
{
    public function __construct(private ResumeDeMission $resume)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    #[AsTwigFunction('resume_de_mission')]
    public function resumeDeMission(GameSave $partie): ?array
    {
        return $this->resume->pour($partie);
    }
}
