<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * La géographie de la région où se joue une partie.
 *
 * Elle n'est **jamais persistée** — c'est du contenu, comme les partenaires
 * commerciaux ou le panthéon : seule la mission l'est, et le catalogue en
 * déduit le reste. Ce service est le seul chemin par lequel on la retrouve,
 * pour que « cette partie a-t-elle un Nil ? » se réponde partout de la même
 * façon.
 *
 * Sans lui, chaque appelant refaisait le détour par le catalogue pour son
 * compte, et l'un d'eux finissait par l'oublier : c'est ce qui laissait
 * annoncer une crue au Sinaï.
 */
final readonly class GeographieDeLaPartie
{
    public function __construct(
        private MissionCatalogue $missions,
    ) {
    }

    public function pour(GameSave $partie): GeographieDeRegion
    {
        $numero = $partie->getMission();

        return $partie->estCampagne() && null !== $numero
            ? $this->missions->get($numero)->geographie
            : LanceurDePartie::geographieDuModeAventure();
    }

    /**
     * **Quatre missions sur dix se jouent loin du fleuve** — Pount, Megiddo,
     * l'Ouadi Hammamat, le Sinaï. Il n'y a là ni crue, ni saison d'inondation,
     * ni divinité du Nil à honorer : annoncer une crue au Sinaï, ou laisser
     * porter une offrande à Hâpi dans un désert, promettait un effet qui ne
     * pouvait pas se produire.
     *
     * Le Fayoum n'en est pas : le Bahr Youssef, une branche du Nil, alimente
     * le lac Moeris et la région monte avec la crue. L'y avoir compté parmi
     * les terres sèches lui retirait sa seule route commerciale — fluviale,
     * donc suspendue à un Port — et rendait Sobek inerte dans la ville dont il
     * est le dieu.
     */
    public function connaitLaCrue(GameSave $partie): bool
    {
        return $this->pour($partie)->connaitLaCrue();
    }
}
