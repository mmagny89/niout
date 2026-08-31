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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Randomizer $hasard = new Randomizer(),
    ) {
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
     * Un indice de terrain qu'on n'a pas encore. Le tirage passe par le
     * `Randomizer` injecté, comme la crue et les candidats : semé en test, il
     * rend la fouille reproductible.
     */
    private function tirerUnIndiceDeTerrain(GameSave $partie): ?Indice
    {
        $ville = $partie->getVille();
        $restants = [];

        foreach (Indice::cases() as $indice) {
            if (SourceDIndice::Terrain !== $indice->source()) {
                continue;
            }

            if ($ville->dossierDe($indice->enquete())?->contient($indice) ?? false) {
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
