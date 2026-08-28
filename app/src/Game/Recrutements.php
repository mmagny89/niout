<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Poster une offre, choisir un chef, le renvoyer (doc 03, doc 05).
 *
 * **Seuls les chefs se recrutent par offre** (décision de la joueuse, conforme
 * au doc 05 : « les chefs recrutent des travailleurs disponibles »). Les
 * manœuvres se puiseront dans le vivier d'actifs de la ville au lot 4.4 — le
 * joueur n'embauche pas ses ouvriers un par un.
 *
 * Quatre règles fixent le cadre, dont trois viennent des documents :
 *
 * - **Poster est libre** : ni quinzaine, ni deben (doc 05). L'annonce ne coûte
 *   rien, c'est le chef qu'on retient qui coûtera.
 * - **Le poste est pourvu à la quinzaine suivante** (doc 05 : « durée d'un
 *   recrutement une fois le candidat choisi : 1 cycle »).
 * - **Le nombre de postes suit le niveau du bâtiment** :
 *   `arrondiSupérieur(niveau / 3)` (doc 01).
 * - **Un bâtiment sans spécialité ne se dirige pas** — Résidence familiale,
 *   Quartier d'habitation, Auberge, les trois pour lesquels le doc 03 ne liste
 *   aucune spécialité. La famille les tient elle-même ; leur poster une offre
 *   n'aurait aucun sens. C'est le seul de ces quatre points qui soit une
 *   déduction et non une ligne de document.
 *
 * **Le chef s'installe avec sa maisonnée, et repart avec elle.** Sans ce
 * second volet, embaucher puis renvoyer serait un moyen gratuit de peupler la
 * ville — bien moins cher que l'appel d'habitants, qu'il rendrait inutile.
 */
final readonly class Recrutements
{
    public function __construct(
        private GenerateurDeCandidat $generateur,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Poste une offre pour diriger ce bâtiment, et fige son tirage.
     *
     * @throws RecrutementImpossible
     */
    public function poster(GameSave $partie, TypeDeBatiment $type): JobOffer
    {
        $ville = $partie->getVille();
        $batiment = $ville->batimentDeType($type);

        if (null === $batiment) {
            throw new RecrutementImpossible(\sprintf('Vous n\'avez pas de %s à confier à qui que ce soit.', $type->libelle()));
        }

        if ([] === SpecialiteDeChef::pour($type)) {
            throw new RecrutementImpossible(\sprintf('Le %s ne se confie pas : votre famille le tient elle-même.', $type->libelle()));
        }

        if (null !== $ville->offrePour($type)) {
            throw new RecrutementImpossible(\sprintf('Une annonce est déjà affichée pour le %s. Tranchez-la d\'abord.', $type->libelle()));
        }

        if (0 === $this->postesLibres($batiment)) {
            throw new RecrutementImpossible(\sprintf('Le %s a déjà ses %d chef%s. Montez-le d\'un niveau pour en employer davantage.', $type->libelle(), $batiment->nombreDeChefs(), $batiment->nombreDeChefs() > 1 ? 's' : ''));
        }

        $offre = new JobOffer($ville, $type, $this->generateur->pourUneOffre($type));
        $ville->ajouterOffre($offre);

        $this->entityManager->persist($offre);
        $this->entityManager->flush();

        return $offre;
    }

    /**
     * Retire une annonce sans embaucher personne. Les candidats sont perdus :
     * reposter en tire de nouveaux, ce qui est la seule façon de relancer les
     * dés — et elle passe par un renoncement explicite.
     */
    public function retirer(JobOffer $offre): void
    {
        $offre->getVille()->retirerOffre($offre);

        $this->entityManager->remove($offre);
        $this->entityManager->flush();
    }

    /**
     * Retient un candidat. Sa maisonnée s'installe aussitôt ; lui prend son
     * poste à la quinzaine suivante.
     *
     * @throws RecrutementImpossible
     */
    public function embaucher(GameSave $partie, JobOffer $offre, int $rang): Employee
    {
        $ville = $partie->getVille();
        $candidat = $offre->candidat($rang);

        if (null === $candidat) {
            throw new RecrutementImpossible('Ce candidat ne s\'est pas présenté.');
        }

        $batiment = $ville->batimentDeType($offre->getType());

        if (null === $batiment || 0 === $this->postesLibres($batiment)) {
            throw new RecrutementImpossible(\sprintf('Le %s n\'a plus de poste à pourvoir.', $offre->getType()->libelle()));
        }

        // Le même verrou que pour l'appel d'habitants : on ne fait pas venir
        // des gens qu'on ne peut pas loger. Le chef amène une maisonnée
        // entière, pas seulement lui.
        if ($ville->manqueDeLogements()) {
            throw new RecrutementImpossible('Vos maisons sont pleines. Un chef s\'installe avec les siens : montez le Quartier d\'habitation.');
        }

        $employe = new Employee($ville, $offre->getType(), $candidat, $partie->getCycle() + 1);
        $ville->ajouterEmploye($employe);
        $ville->accueillir($candidat->actifsAmenes, $candidat->inactifsAmenes, 0);

        $ville->retirerOffre($offre);

        $this->entityManager->persist($employe);
        $this->entityManager->remove($offre);
        $this->entityManager->flush();

        return $employe;
    }

    /**
     * Renvoie un chef. Sa maisonnée s'en va avec lui — c'est ce qui empêche
     * d'embaucher pour peupler.
     */
    public function renvoyer(Employee $employe): void
    {
        $ville = $employe->getVille();

        $ville->laisserPartir($employe->getActifsAmenes(), $employe->getInactifsAmenes());
        $ville->retirerEmploye($employe);

        $this->entityManager->remove($employe);
        $this->entityManager->flush();
    }

    /**
     * Combien de postes de chef restent à pourvoir dans ce bâtiment.
     */
    public function postesLibres(Building $batiment): int
    {
        return max(0, $batiment->nombreDeChefs() - \count(
            $batiment->getVille()->chefsDe($batiment->getType()),
        ));
    }
}
