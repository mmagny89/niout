<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Random\Randomizer;

/**
 * Le prénom des chefs et des Medjaÿ (`docs/plan-pnj-ville-vivante.md`, lot N).
 *
 * **Un prénom se garde, il ne se déduit pas.** Déduit du rang d'un chef dans la
 * ville, il changerait dès qu'un plus ancien s'en va ; déduit de son
 * identifiant, il ne garantirait pas l'unicité avec si peu de prénoms. Un nom
 * qui change sous les yeux du joueur est pire que pas de nom : on le tire donc
 * **une fois**, à la création, et on le range sur l'entité — comme le prénom du
 * chef de famille.
 *
 * **Un prénom n'est porté qu'une fois dans une ville.** Ni par deux chefs, ni
 * par un chef et un Medjaÿ, ni par le chef de famille, ni par un candidat d'une
 * annonce ouverte, ni par le rival installé. Quand tout le fonds est pris, on
 * ajoute la filiation (« Hori, fils de Sennedjem ») : c'est ainsi que les
 * Égyptiens se distinguaient, et ce n'est pas une invention.
 *
 * Le tirage passe par un `Randomizer` injecté, semé en test.
 */
final readonly class Prenoms
{
    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Tout ce qui est déjà porté dans la ville de cette partie — prénoms
     * simples **et** filiations.
     *
     * @return list<string>
     */
    public static function pris(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $pris = [];

        foreach ($ville->getEmployes() as $chef) {
            $pris[] = $chef->getPrenom();
        }

        foreach ($ville->getMedjays() as $medjay) {
            $pris[] = $medjay->getPrenom();
        }

        foreach ($ville->getOffres() as $offre) {
            foreach ($offre->candidats() as $candidat) {
                $pris[] = $candidat->prenom;
            }
        }

        $pris[] = $partie->getFamille()->getChefDeFamille();

        // Le rival s'appelle « Panehsy le marchand » : seul son prénom compte.
        $pris[] = explode(' ', $ville->getRival()?->getNom() ?? '')[0];

        return array_values(array_filter($pris, static fn (?string $nom): bool => null !== $nom && '' !== $nom));
    }

    /**
     * Un prénom que personne ne porte dans cette ville.
     *
     * @param bool         $masculin à vrai, le fonds masculin seul — les Medjaÿ sont des hommes d'armes
     * @param list<string> $reserves ce qui est déjà tiré sans être encore rangé (les candidats d'une même annonce)
     */
    public function libre(GameSave $partie, bool $masculin = false, array $reserves = []): string
    {
        $complets = [...self::pris($partie), ...$reserves];
        $fonds = $masculin ? PrenomEgyptien::ordinairesMasculins() : PrenomEgyptien::ordinaires();

        // « Hori, fils de Sennedjem » occupe aussi « Hori » : sans quoi un chef
        // parti rendrait le prénom à un homonyme encore là.
        $bases = array_map(static fn (string $nom): string => explode(',', $nom)[0], $complets);
        $libres = array_values(array_diff($fonds, $bases));

        if ([] !== $libres) {
            return $libres[$this->hasard->getInt(0, \count($libres) - 1)];
        }

        return $this->avecFiliation($fonds, $complets);
    }

    /**
     * Quelques prénoms égyptiens libres, à proposer quand on nomme quelqu'un soi-même : **distincts** entre eux et de
     * tout ce qui est déjà porté dans la ville.
     *
     * @return list<string>
     */
    public function propositions(GameSave $partie, bool $masculin = false, int $nombre = 6): array
    {
        $propositions = [];

        for ($i = 0; $i < $nombre * 4 && \count($propositions) < $nombre; ++$i) {
            $prenom = $this->libre($partie, $masculin, $propositions);

            if (!\in_array($prenom, $propositions, true)) {
                $propositions[] = $prenom;
            }
        }

        return $propositions;
    }

    /**
     * Ce prénom est-il déjà porté dans la ville ? (Sans tenir compte de la casse, ni de la filiation.).
     */
    public static function estPris(GameSave $partie, string $prenom): bool
    {
        $base = static fn (string $nom): string => mb_strtolower(trim(explode(',', $nom)[0]));

        return \in_array($base($prenom), array_map($base, self::pris($partie)), true);
    }

    /**
     * Donne un prénom à chaque candidat d'une annonce, sans que deux la
     * portent.
     *
     * @param list<Candidat> $candidats
     *
     * @return list<Candidat>
     */
    public function nommerLesCandidats(GameSave $partie, array $candidats): array
    {
        $nommes = [];
        $reserves = [];

        // Un chef qui revient garde le prénom qu'il portait : on le réserve d'abord, pour que personne d'autre
        // dans l'annonce ne le reçoive.
        foreach ($candidats as $candidat) {
            if (null !== $candidat->prenom) {
                $reserves[] = $candidat->prenom;
            }
        }

        foreach ($candidats as $candidat) {
            if (null !== $candidat->prenom) {
                $nommes[] = $candidat;

                continue;
            }

            $prenom = $this->libre($partie, false, $reserves);
            $reserves[] = $prenom;
            $nommes[] = $candidat->avecPrenom($prenom);
        }

        return $nommes;
    }

    /**
     * Donne un prénom à qui n'en a pas : les chefs et les Medjaÿ des parties
     * commencées avant que le jeu en distribue. **Idempotent** : qui en a un
     * le garde, et l'appeler deux fois ne change rien. Dans l'ordre des
     * identifiants, pour que le résultat ne dépende pas de l'ordre de lecture.
     *
     * @return int combien de prénoms ont été donnés
     */
    public function completer(GameSave $partie): int
    {
        $ville = $partie->getVille();
        $donnes = 0;

        $chefs = $ville->getEmployes()->toArray();
        usort($chefs, static fn ($a, $b): int => ($a->getId() ?? \PHP_INT_MAX) <=> ($b->getId() ?? \PHP_INT_MAX));

        foreach ($chefs as $chef) {
            if (null === $chef->getPrenom()) {
                $chef->donnerUnPrenom($this->libre($partie));
                ++$donnes;
            }
        }

        $hommes = $ville->getMedjays()->toArray();
        usort($hommes, static fn ($a, $b): int => ($a->getId() ?? \PHP_INT_MAX) <=> ($b->getId() ?? \PHP_INT_MAX));

        foreach ($hommes as $medjay) {
            if (null === $medjay->getPrenom()) {
                $medjay->donnerUnPrenom($this->libre($partie, true));
                ++$donnes;
            }
        }

        return $donnes;
    }

    /**
     * @param list<string> $fonds
     * @param list<string> $complets
     */
    private function avecFiliation(array $fonds, array $complets): string
    {
        $nombre = \count($fonds);
        $depart = $this->hasard->getInt(0, $nombre - 1);

        for ($i = 0; $i < $nombre; ++$i) {
            for ($j = 0; $j < $nombre; ++$j) {
                $fils = $fonds[($depart + $i) % $nombre];
                $pere = $fonds[($depart + $i + 1 + $j) % $nombre];
                $nom = \sprintf('%s, fils de %s', $fils, $pere);

                if ($fils !== $pere && !\in_array($nom, $complets, true)) {
                    return $nom;
                }
            }
        }

        // Un cas absurde — toutes les filiations prises. Un numéro d'ordre
        // plutôt qu'une boucle sans fin.
        $rang = 2;

        while (\in_array(\sprintf('%s (%d)', $fonds[$depart], $rang), $complets, true)) {
            ++$rang;
        }

        return \sprintf('%s (%d)', $fonds[$depart], $rang);
    }
}
