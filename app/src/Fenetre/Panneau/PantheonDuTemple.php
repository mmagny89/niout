<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Divinite;
use App\Game\GeographieDeLaPartie;
use App\Game\Negligence;
use App\Game\Offrandes;
use App\Game\Temple;
use App\Game\TypeDeBatiment;

/**
 * Ce que l'onglet du Temple affiche.
 */
final readonly class PantheonDuTemple
{
    public function __construct(private Offrandes $offrandes, private GeographieDeLaPartie $geographies)
    {
    }

    /**
     * Ce que l'onglet du Temple affiche : le panthéon, ses paliers, et ce qu'il
     * est possible d'offrir.
     *
     * @return array<string, mixed>
     */
    public function donnees(GameSave $partie): array
    {
        $geographie = $this->geographies->pour($partie);
        $ville = $partie->getVille();
        $temple = $ville->batimentDeType(TypeDeBatiment::Temple);

        $pantheon = [];

        foreach (Divinite::pantheon() as $divinite) {
            $suivie = $ville->faveurDe($divinite);
            $pantheon[] = [
                'divinite' => $divinite,
                'faveur' => $ville->faveurEnvers($divinite),
                'palier' => $ville->palierDe($divinite),
                // Un dieu qui commence à se détourner doit le dire avant que
                // son effet ne cesse, sinon le joueur ne l'apprend qu'une fois
                // le palier perdu.
                'seDetourne' => null !== $suivie
                    && $suivie->getQuinzainesSansOffrande() > Negligence::QUINZAINES_DE_GRACE
                    && $suivie->getFaveur() > Negligence::PLANCHER,
                'quinzainesSansOffrande' => $suivie?->getQuinzainesSansOffrande() ?? 0,
                // Le supplément de fête se lit **avant** de donner, comme le
                // prix d'un ordre commercial montre son effet avant
                // l'engagement.
                'supplementDeFete' => Offrandes::supplementDeFete($partie->dateDeJeu(), $divinite),
                // Deux manques distincts : un système à venir, et un domaine
                // qui n'existe pas dans cette région. Le second refuse
                // l'offrande, le premier l'accepte.
                'sansDomaineIci' => $divinite->estSansDomaineIci($geographie),
                'attente' => $divinite->attenteDans($geographie),
            ];
        }

        return [
            'pantheon' => $pantheon,
            'aUnTemple' => null !== $temple,
            'niveauDuTemple' => $temple?->getNiveau() ?? 0,
            'divinitesPortables' => Temple::divinitesPortables($ville),
            'plafond' => Temple::plafondDeFaveur($ville),
            'honorees' => $ville->divinitesHonorees(),
            'corbeille' => null !== $temple ? $this->offrandes->corbeillePour($partie) : [],
            'pointsParOffrande' => Offrandes::POINTS_PAR_OFFRANDE,
            'debenParOffrande' => Offrandes::DEBEN_PAR_OFFRANDE,
            'fete' => $partie->feteEnCours(),
            'pointsDeFete' => Offrandes::POINTS_DE_FETE,
        ];
    }
}
