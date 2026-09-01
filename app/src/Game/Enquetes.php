<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\DossierDEnquete;
use App\Entity\GameSave;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Ramasser des indices (doc 10).
 *
 * **Le contenu `Evenement` d'une case trouve enfin son emploi.** Il est posé
 * par la génération de carte depuis le lot 3.2 et ne menait nulle part : une
 * case où « quelque chose se trame » se fouille désormais, et rend un indice.
 * C'est le point d'entrée que le doc 10 lui donne.
 *
 * **Une case ne se fouille qu'une fois.** Sans quoi la même case rendrait
 * tout le dossier, et l'exploration cesserait d'avoir un coût.
 *
 * Les témoignages, eux, attendent l'Émissaire (lot 7.5) : ils sont dans le
 * catalogue, ils ne se ramassent pas encore sur le terrain.
 */
final readonly class Enquetes
{
    /**
     * Ce qu'une déduction erronée coûte sur une enquête qui se rejoue : deux
     * cycles, et **aucune ressource** (doc 10). Le temps est la seule monnaie
     * d'une erreur — c'est ce qui encourage à retenter sans punir durement.
     */
    public const int RETARD_DUNE_ERREUR = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Rivaux $rivaux,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Ce qu'une erreur coûte réellement. **Thot éclaire ce que les écrits
     * dissimulent** (doc 07) : sous son regard, les scribes retrouvent le fil
     * plus vite. Jamais nul pour autant — une erreur sans conséquence n'en
     * serait plus une.
     */
    public static function retardDUneErreur(GameSave $partie): int
    {
        return $partie->getVille()->palierDe(Divinite::Thot)->estAuDessusDuNeutre()
            ? 1
            : self::RETARD_DUNE_ERREUR;
    }

    /**
     * Les dossiers ouverts, dans l'ordre où le joueur veut les lire : la
     * principale d'abord, elle porte le fil rouge.
     *
     * @return list<DossierDEnquete>
     */
    public function dossiers(GameSave $partie): array
    {
        $dossiers = array_values($partie->getVille()->getDossiers()->toArray());

        usort($dossiers, static function (DossierDEnquete $a, DossierDEnquete $b): int {
            $rang = ($b->getEnquete()->estPrincipale() ? 1 : 0) <=> ($a->getEnquete()->estPrincipale() ? 1 : 0);

            return 0 !== $rang ? $rang : $a->getEnquete()->libelle() <=> $b->getEnquete()->libelle();
        });

        return $dossiers;
    }

    /**
     * **Il faut des scribes pour enquêter.** Le doc 01 le dit de la Maison des
     * scribes elle-même — « déchiffrage des inscriptions et conduite des
     * enquêtes » —, et sans elle un indice n'irait dans aucun dossier :
     * fouiller rendrait une phrase que rien ne retiendrait.
     */
    public function peutFouiller(City $ville, Zone $zone): bool
    {
        return $ville->possede(TypeDeBatiment::MaisonDesScribes)
            && $zone->estDecouverte()
            && ContenuDeZone::Evenement === $zone->getContenu()
            && !$zone->indiceRecueilli();
    }

    /**
     * Fouille une case, et verse au dossier l'indice qu'on y trouve.
     *
     * @throws EnqueteImpossible
     */
    public function fouiller(GameSave $partie, Zone $zone): Indice
    {
        if (!$this->peutFouiller($partie->getVille(), $zone)) {
            throw new EnqueteImpossible(match (true) {
                !$partie->getVille()->possede(TypeDeBatiment::MaisonDesScribes) => 'Sans Maison des scribes, personne ne consignerait ce qu\'on trouverait.', $zone->indiceRecueilli() => 'Cette case a déjà livré ce qu\'elle avait.', default => 'Il n\'y a rien à fouiller ici.',
            });
        }

        $indice = $this->tirerUnIndiceDeTerrain($partie);

        if (null === $indice) {
            throw new EnqueteImpossible('Vos gens fouillent, et ne trouvent rien de neuf.');
        }

        $zone->marquerFouillee();
        $partie->getVille()->ouvrirLeDossierDe($indice->enquete())->verser($indice);

        $this->entityManager->flush();

        return $indice;
    }

    /**
     * Conclut une enquête.
     *
     * **Se tromper ne se paie pas de la même façon selon l'enquête** (décision
     * de la joueuse) : une **principale** porte le fil rouge d'une mission, son
     * échec définitif bloquerait la campagne — elle se rejoue, au prix des deux
     * cycles de retard du doc 10. Une **secondaire** se perd pour de bon, et
     * c'est ce qui donne du poids à une déduction : sans ce risque, conclure au
     * hasard puis recommencer serait toujours la meilleure stratégie.
     *
     * Dans les deux cas, **aucune ressource n'est retirée** (doc 10) et le
     * dénouement est dit : le vrai gain d'une enquête est de savoir ce qui
     * s'est passé.
     *
     * @return array{juste: bool, denouement: string, recompense: int, definitif: bool}
     *
     * @throws EnqueteImpossible
     */
    public function conclure(GameSave $partie, Enquete $enquete, string $conclusion): array
    {
        $dossier = $partie->getVille()->dossierDe($enquete);

        if (null === $dossier) {
            throw new EnqueteImpossible('Vous n\'avez rien sur cette affaire.');
        }

        if (StatutDEnquete::EnCours !== $dossier->getStatut()) {
            throw new EnqueteImpossible('Cette affaire est close.');
        }

        if (!\in_array($conclusion, $enquete->conclusions(), true)) {
            throw new EnqueteImpossible('Ce n\'est pas une des conclusions envisagées.');
        }

        if ($dossier->concordantsReunis() < $enquete->indicesRequis()) {
            throw new EnqueteImpossible('Vous n\'en savez pas encore assez pour trancher.');
        }

        if ($partie->getCycle() < $dossier->getRejouableAuCycle()) {
            throw new EnqueteImpossible(\sprintf('Vos scribes reprennent le dossier depuis le début : revenez dans %d quinzaine(s).', $dossier->getRejouableAuCycle() - $partie->getCycle()));
        }

        $juste = $conclusion === $enquete->bonneConclusion();

        if ($juste) {
            $dossier->conclure(StatutDEnquete::Resolue);
            $partie->getVille()->crediterRessources([Ressource::Deben->value => $enquete->recompenseEnDeben()]);
            $partie->getFamille()->ajusterRenommee(1);

            // La troisième issue du doc 08 : le rival est démonté, et il ne
            // revient pas — contrairement à celui qu'on paie ou qu'on ignore.
            if ($enquete->viseUnRival()) {
                $this->rivaux->neutraliserParLEnquete($partie);
            }
        } elseif ($enquete->estPrincipale()) {
            $dossier->retarderJusquAu($partie->getCycle() + self::retardDUneErreur($partie));
        } else {
            $dossier->conclure(StatutDEnquete::Echouee);
        }

        $this->entityManager->flush();

        return [
            'juste' => $juste,
            'denouement' => $enquete->denouement(),
            'recompense' => $juste ? $enquete->recompenseEnDeben() : 0,
            'definitif' => !$juste && !$enquete->estPrincipale(),
        ];
    }

    /**
     * Ce qu'un émissaire rapporte : un témoignage qu'on n'a pas encore
     * entendu. Nul quand il n'en reste aucun — l'émissaire revient bredouille,
     * et l'écran le dit plutôt que de faire semblant.
     */
    public function recueillirUnTemoignage(GameSave $partie): ?Indice
    {
        $indice = $this->tirerUnIndice($partie, SourceDIndice::Temoignage);

        if (null === $indice) {
            return null;
        }

        $partie->getVille()->ouvrirLeDossierDe($indice->enquete())->verser($indice);

        return $indice;
    }

    /**
     * Un indice de terrain qu'on n'a pas encore. Le tirage passe par le
     * `Randomizer` injecté, comme la crue et les candidats : semé en test, il
     * rend la fouille reproductible.
     */
    private function tirerUnIndiceDeTerrain(GameSave $partie): ?Indice
    {
        return $this->tirerUnIndice($partie, SourceDIndice::Terrain);
    }

    private function tirerUnIndice(GameSave $partie, SourceDIndice $source): ?Indice
    {
        $ville = $partie->getVille();
        $restants = [];

        foreach (Indice::cases() as $indice) {
            if ($source !== $indice->source()) {
                continue;
            }

            if ($ville->dossierDe($indice->enquete())?->contient($indice) ?? false) {
                continue;
            }

            // On ne démonte pas un marchand avant qu'il n'arrive.
            if ($indice->enquete()->viseUnRival() && null === $ville->getRival()) {
                continue;
            }

            $restants[] = $indice;
        }

        if ([] === $restants) {
            return null;
        }

        return $restants[$this->hasard->getInt(0, \count($restants) - 1)];
    }
}
