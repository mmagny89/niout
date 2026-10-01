<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * L'impôt mensuel : ce que le pharaon laisse à la ville sur le travail de ses
 * habitants, pour qu'une ville sans commerce ne meure pas faute de deben.
 *
 * **C'est un filet, pas une source de richesse.** Sans lui, la monnaie ne
 * venait que du Marché et des caravanes : une famille qui tardait à ouvrir une
 * route voyait la paie dépasser la bourse, les équipes s'arrêter, puis le
 * mécontentement s'installer — une impasse que rien ne signalait à l'avance.
 * L'impôt couvre de quoi tenir, pas de quoi s'enrichir : la vraie fortune reste
 * celle des routes.
 *
 * Les scribes du Nouvel Empire prélevaient en nature sur les récoltes ; le
 * jeu convertit en deben, comme partout (`Ressource::Deben`).
 *
 * Il se perçoit à la **fin de chaque mois**, soit une quinzaine sur deux : les
 * cinq jours épagomènes n'appartiennent à aucun mois et ne rapportent rien.
 *
 * **Les scribes lèvent l'impôt** : leur degré d'érudition (`PalierDErudition`)
 * le majore, ce qui donne enfin un intérêt de jeu à apprendre à lire.
 *
 * Objet de calcul sans état, jamais persisté : le montant se déduit de la
 * population et du calendrier.
 */
final readonly class Impots
{
    /**
     * Deben perçus par actif valide à chaque fin de mois. **Valeur inventée**,
     * à calibrer en playtest : un travailleur coûte `SALAIRE_JUSTE` par
     * quinzaine, donc deux par mois — l'impôt en couvre la moitié, de sorte
     * que sans commerce la ville ralentit sans mourir. Les malades ne paient
     * pas : ils ne travaillent pas.
     */
    public const int DEBEN_PAR_ACTIF_ET_PAR_MOIS = 1;

    /**
     * Vrai si la quinzaine qui se solde clôt un mois.
     */
    public function cloture(GameSave $partie): bool
    {
        $date = $partie->dateDeJeu();

        return null !== $date->numeroDeMois
            && 0 === $date->rangDansLAnnee % DateDeJeu::CYCLES_PAR_MOIS;
    }

    /**
     * Ce que le prochain passage de mois rapportera, au chiffre d'aujourd'hui.
     */
    public function montantPrevu(GameSave $partie): int
    {
        $ville = $partie->getVille();
        $base = $ville->actifsValides() * self::DEBEN_PAR_ACTIF_ET_PAR_MOIS;

        // Les scribes lèvent l'impôt : plus ils sont lettrés, mieux ils s'y
        // prennent (`PalierDErudition`). Une multiplication, une division —
        // jamais deux arrondis enchaînés.
        $bonus = PalierDErudition::pour($ville, $partie->getCycle())->bonusDImpot();

        return intdiv($base * (100 + $bonus), 100);
    }

    /**
     * Combien de quinzaines avant la prochaine perception, la quinzaine en
     * cours comprise : 1 si elle clôt un mois.
     */
    public function quinzainesAvantLaPerception(GameSave $partie): int
    {
        $date = $partie->dateDeJeu();

        // Les jours épagomènes précèdent un mois qui s'ouvre : il faut en
        // traverser deux pour atteindre sa fin.
        if (null === $date->numeroDeMois) {
            return DateDeJeu::CYCLES_PAR_MOIS + 1;
        }

        return DateDeJeu::CYCLES_PAR_MOIS - (($date->rangDansLAnnee - 1) % DateDeJeu::CYCLES_PAR_MOIS);
    }

    /**
     * Perçoit l'impôt si le mois se clôt, et dit au joueur ce qu'il a reçu.
     *
     * @return list<string>
     */
    public function percevoir(GameSave $partie): array
    {
        if (!$this->cloture($partie)) {
            return [];
        }

        $montant = $this->montantPrevu($partie);

        if ($montant < 1) {
            return [];
        }

        $partie->getVille()->crediterRessources([Ressource::Deben->value => $montant]);

        return [\sprintf(
            'Les scribes du pharaon ont levé l\'impôt du mois de %s : %d deben rentrent en caisse.',
            $partie->dateDeJeu()->nomDeMois,
            $montant,
        )];
    }
}
