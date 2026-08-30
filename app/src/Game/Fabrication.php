<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\OrdreDeFabrication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fabriquer : lancer un ordre, le faire avancer, en récolter les pièces
 * (doc 01, doc 08).
 *
 * **L'Atelier et la Forge partagent tout** — ordres, lots, déblocage par
 * niveau, rythme donné par les bras. Ils ne diffèrent que par ce qu'ils savent
 * faire, et c'est la recette qui dit où elle se travaille
 * (`Recette::batiment()`). Deux services auraient été deux fois la même chose,
 * avec deux occasions de diverger.
 *
 * **Quatre règles, et chacune tient à quelque chose :**
 *
 * - **Les matières sont débitées à l'engagement**, comme un chantier. On ne
 *   réserve pas, on paie : sans cela, un joueur pourrait lancer dix ordres avec
 *   les ressources d'un seul et voir lesquels aboutissent.
 * - **Les pièces n'entrent qu'à l'achèvement.** C'est la règle des champs :
 *   rien ne rentre hors de la récolte.
 * - **Un seul ordre à la fois.** L'Atelier est un lieu, pas une file d'attente,
 *   et c'est ce qui donne son coût d'opportunité à la fabrication — tisser,
 *   c'est ne pas cuire.
 * - **Le niveau ouvre les recettes et la taille des ordres** (doc 01) : la
 *   complexité de l'artisanat croît avec le bâtiment, et un grand atelier
 *   travaille par plus gros lots.
 *
 * Le **rythme** vient des bras et des chefs, par le canal habituel
 * (`EffetDeChef::qualiteDeDirection()`) : un Atelier désert met deux fois plus
 * de temps, sans jamais s'arrêter — « rien ne s'éteint faute d'employés ».
 */
final readonly class Fabrication
{
    /**
     * Lots qu'un niveau autorise dans un même ordre. **Valeur inventée** : le
     * doc 01 dit que le niveau élargit la production sans le chiffrer.
     */
    public const int LOTS_PAR_NIVEAU = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Engage un ordre : débite les matières, occupe l'Atelier.
     *
     * @throws FabricationImpossible
     */
    public function lancer(GameSave $partie, Recette $recette, int $lots): OrdreDeFabrication
    {
        $ville = $partie->getVille();
        $type = $recette->batiment();
        $atelier = $ville->batimentDeType($type);

        if (null === $atelier) {
            throw new FabricationImpossible(\sprintf('Il vous faut %s %s pour cela.', TypeDeBatiment::Forge === $type ? 'une' : 'un', $type->libelle()));
        }

        if (null !== $ville->ordreDeFabricationDe($type)) {
            throw new FabricationImpossible(\sprintf('Votre %s a déjà un ouvrage en cours.', $type->libelle()));
        }

        if ($recette->niveauRequis() > $atelier->getNiveau()) {
            throw new FabricationImpossible(\sprintf('%s demande %s de niveau %d ; le vôtre est au %d.', $recette->libelle(), $type->libelle(), $recette->niveauRequis(), $atelier->getNiveau()));
        }

        $maximum = self::lotsMaximum($atelier->getNiveau());

        if ($lots < 1 || $lots > $maximum) {
            throw new FabricationImpossible(\sprintf('Votre %s ne mène pas plus de %d lot%s à la fois.', $type->libelle(), $maximum, $maximum > 1 ? 's' : ''));
        }

        $matieres = self::matieresPour($recette, $lots);

        if (!$ville->debiterRessources($matieres)) {
            throw new FabricationImpossible(\sprintf('Il vous manque %s.', implode(', ', $ville->manquesDe($matieres))));
        }

        $ordre = new OrdreDeFabrication($ville, $recette, $lots);
        $ville->ajouterOrdreDeFabrication($ordre);

        $this->entityManager->persist($ordre);
        $this->entityManager->flush();

        return $ordre;
    }

    /**
     * Fait avancer l'ouvrage d'une quinzaine, et le livre s'il est fini.
     *
     * Ne persiste rien, comme les autres résolutions de cycle : `PassageDeCycle`
     * réunit tout en une seule écriture.
     *
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $messages = [];

        foreach (Recette::batimentsQuiFabriquent() as $type) {
            $messages = [...$messages, ...$this->avancerUnAtelier($partie, $type)];
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function avancerUnAtelier(GameSave $partie, TypeDeBatiment $type): array
    {
        $ville = $partie->getVille();
        $ordre = $ville->ordreDeFabricationDe($type);

        if (null === $ordre) {
            return [];
        }

        $ordre->avancerDUnCycle(
            EffetDeChef::qualiteDeDirection($ville, $type, $partie->getCycle()),
        );

        if (!$ordre->estAcheve()) {
            return [];
        }

        $recette = $ordre->getRecette();
        $pieces = [$recette->produit()->value => $ordre->piecesAttendues()];

        $perdu = $ville->surplusRefuse($pieces);
        $ville->crediterRessources($pieces);
        $ville->retirerOrdreDeFabrication($ordre);

        $messages = [\sprintf(
            'L\'Atelier livre %d %s.',
            $ordre->piecesAttendues(),
            $recette->produit()->libelle(),
        )];

        if ([] !== $perdu) {
            $messages[] = \sprintf(
                'Vos réserves débordent : %s se perdent faute de place.',
                self::enoncer($perdu),
            );
        }

        return $messages;
    }

    /**
     * Les recettes que la ville peut lancer, et ce qu'elles réclament — de quoi
     * remplir l'écran sans que le gabarit ait à recalculer.
     *
     * @return list<array{recette: Recette, matieres: array<string, int>, realisable: bool, empechement: ?string}>
     *                                                                                                             `matieres` est indexé par libellé, prêt à l'affichage
     */
    public function offrePour(GameSave $partie, TypeDeBatiment $type): array
    {
        $ville = $partie->getVille();
        $atelier = $ville->batimentDeType($type);

        if (null === $atelier) {
            return [];
        }

        $offre = [];

        foreach (Recette::pour($type, $atelier->getNiveau()) as $recette) {
            $matieres = self::matieresPour($recette, 1);
            $manques = $ville->manquesDe($matieres);

            $offre[] = [
                'recette' => $recette,
                'matieres' => self::enLibelles($matieres),
                'realisable' => [] === $manques,
                'empechement' => [] === $manques ? null : \sprintf('Il vous manque %s.', implode(', ', $manques)),
            ];
        }

        return $offre;
    }

    public static function lotsMaximum(int $niveau): int
    {
        return max(1, self::LOTS_PAR_NIVEAU * $niveau);
    }

    /**
     * Ce qu'un ordre de `$lots` lots consomme, deben compris.
     *
     * @return array<string, int>
     */
    public static function matieresPour(Recette $recette, int $lots): array
    {
        $matieres = [Ressource::Deben->value => $recette->debenDunLot() * $lots];

        foreach ($recette->ingredientsDunLot() as $valeur => $quantite) {
            $matieres[$valeur] = $quantite * $lots;
        }

        return $matieres;
    }

    /**
     * Le même détail, mais lisible : les gabarits n'ont pas à traduire des
     * valeurs d'énumération.
     *
     * @param array<string, int> $ressources
     *
     * @return array<string, int> libellé => quantité
     */
    private static function enLibelles(array $ressources): array
    {
        $detail = [];

        foreach ($ressources as $valeur => $quantite) {
            $detail[Ressource::from($valeur)->libelle()] = $quantite;
        }

        return $detail;
    }

    /**
     * @param array<string, int> $ressources
     */
    private static function enoncer(array $ressources): string
    {
        $parts = [];

        foreach ($ressources as $valeur => $quantite) {
            $parts[] = \sprintf('%d %s', $quantite, Ressource::from($valeur)->libelle());
        }

        return implode(', ', $parts);
    }
}
