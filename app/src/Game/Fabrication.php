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
 * - **Un seul ordre à la fois, par travailleur.** Un poste n'est pas une file
 *   d'attente, et c'est ce qui donne son coût d'opportunité à la fabrication —
 *   tisser, c'est ne pas cuire avec ces mains-là. Un bâtiment mène autant
 *   d'ordres de front que de travailleurs en poste (`postesDe()`), chacun avec
 *   sa consigne ; un bâtiment désert garde un poste, mais ne fonctionne pas.
 * - **Le niveau ouvre les recettes et la taille des ordres** (doc 01) : la
 *   complexité de l'artisanat croît avec le bâtiment, et un grand atelier
 *   travaille par plus gros lots.
 *
 * Le **rythme** vient des bras et des chefs, par le canal habituel
 * (`EffetDeChef::qualiteDeDirection()`) : un Atelier au complet mais sans chef
 * met deux fois plus de temps ; **sans aucun travailleur, il ne fonctionne pas.**
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
    public function lancer(GameSave $partie, Recette $recette, int $lots, int $poste = 1): OrdreDeFabrication
    {
        $ordre = $this->engager($partie, $recette, $lots, $poste);

        $this->entityManager->persist($ordre);
        $this->entityManager->flush();

        return $ordre;
    }

    /**
     * Le même engagement, sans écriture : c'est ce que réclame une relance en
     * cours de cycle, où `PassageDeCycle` réunit tout en une seule écriture.
     *
     * @throws FabricationImpossible
     */
    private function engager(GameSave $partie, Recette $recette, int $lots, int $poste = 1, ?OrdreDeFabrication $aReemployer = null): OrdreDeFabrication
    {
        $ville = $partie->getVille();
        $type = $recette->batiment();
        $atelier = $ville->batimentDeType($type);

        if (null === $atelier) {
            throw new FabricationImpossible(\sprintf('Il vous faut %s %s pour cela.', TypeDeBatiment::Forge === $type ? 'une' : 'un', $type->libelle()));
        }

        // Sans un seul travailleur, le bâtiment ne fonctionne pas : on n'engage pas des matières
        // dans un ouvrage que personne ne mènera.
        if (Effectifs::rendementDe($ville, $type, $partie->getCycle()) < 1) {
            throw new FabricationImpossible(\sprintf('Votre %s n\'a aucun travailleur : il ne fonctionne pas.', $type->libelle()));
        }

        if ($poste < 1 || $poste > self::postesDe($partie, $type)) {
            throw new FabricationImpossible(\sprintf('Votre %s n\'a pas de travailleur au poste %d.', $type->libelle(), $poste));
        }

        $enCours = $ville->ordreDeFabricationDe($type, $poste);

        if (null !== $enCours && $enCours !== $aReemployer) {
            throw new FabricationImpossible(\sprintf('Ce travailleur de votre %s a déjà un ouvrage en cours.', $type->libelle()));
        }

        if ($recette->niveauRequis() > $atelier->getNiveau()) {
            throw new FabricationImpossible(\sprintf('%s demande %s de niveau %d ; le vôtre est au %d.', $recette->libelle(), $type->libelle(), $recette->niveauRequis(), $atelier->getNiveau()));
        }

        $supplementaire = $recette->deblocageSupplementaire();

        if (null !== $supplementaire) {
            [$autre, $niveauExige] = $supplementaire;
            $niveauReel = $ville->batimentDeType($autre)?->getNiveau() ?? 0;

            if ($niveauReel < $niveauExige) {
                throw new FabricationImpossible(\sprintf('%s demande aussi %s de niveau %d ; le vôtre est au %d.', $recette->libelle(), $autre->libelle(), $niveauExige, $niveauReel));
            }
        }

        $maximum = self::lotsMaximum($atelier->getNiveau());

        if ($lots < 1 || $lots > $maximum) {
            throw new FabricationImpossible(\sprintf('Votre %s ne mène pas plus de %d lot%s à la fois.', $type->libelle(), $maximum, $maximum > 1 ? 's' : ''));
        }

        $matieres = self::matieresPour($recette, $lots);

        if (!$ville->debiterRessources($matieres)) {
            throw new FabricationImpossible(\sprintf('Il vous manque %s.', implode(', ', $ville->manquesDe($matieres))));
        }

        if (null !== $aReemployer) {
            return $aReemployer->repartir($recette, $lots);
        }

        $ordre = new OrdreDeFabrication($ville, $recette, $lots, $poste);
        $ville->ajouterOrdreDeFabrication($ordre);

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

        $ville = $partie->getVille();

        foreach (Recette::batimentsQuiFabriquent() as $type) {
            // Chaque travailleur mène son ouvrage : on parcourt les postes qui ont un ordre ou une
            // consigne, y compris ceux que la ville ne tient plus (un ordre en cours va à son terme).
            $postes = array_unique([
                ...array_map(static fn ($o): int => $o->getPoste(), $ville->ordresDeFabricationDe($type)),
                ...array_map(static fn ($c): int => $c->getPoste(), $ville->consignesDeFabricationDe($type)),
            ]);
            sort($postes);

            foreach ($postes as $poste) {
                $messages = [...$messages, ...$this->avancerUnPoste($partie, $type, $poste)];
            }
        }

        return $messages;
    }

    /**
     * Le nombre de travailleurs qui tiennent un atelier, donc d'ordres qu'il mène de front.
     * Au moins un poste est compté : l'atelier désert garde sa place, mais ne fonctionne pas
     * (0 %), et `engager()` refuse alors tout ordre.
     */
    public static function postesDe(GameSave $partie, TypeDeBatiment $type): int
    {
        $repartition = Effectifs::repartir($partie->getVille(), $partie->getCycle());

        return max(1, $repartition[$type->value]['affectes'] ?? 0);
    }

    /**
     * @return list<string>
     */
    private function avancerUnPoste(GameSave $partie, TypeDeBatiment $type, int $poste): array
    {
        $ville = $partie->getVille();
        $ordre = $ville->ordreDeFabricationDe($type, $poste);

        if (null === $ordre) {
            return $this->tenterLaConsigne($partie, $type, $poste)['messages'];
        }

        // La recette est passée : un Brasseur presse la bière, pas le papyrus.
        $ordre->avancerDUnCycle(
            EffetDeChef::qualiteDeDirection($ville, $type, $partie->getCycle(), $ordre->getRecette()),
        );

        if (!$ordre->estAcheve()) {
            return [];
        }

        $recette = $ordre->getRecette();
        $pieces = [$recette->produit()->value => $ordre->piecesAttendues()];

        $perdu = $ville->surplusRefuse($pieces);
        $ville->crediterRessources($pieces);

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

        // La consigne relance dans la foulée de la livraison : sans cela, elle
        // ferait perdre une quinzaine à chaque ouvrage. Elle **réemploie la
        // ligne** de l'ordre livré, jamais une nouvelle : Doctrine insérant
        // avant de supprimer, une suppression suivie d'une insertion dans la
        // même quinzaine ferait sauter l'unicité par poste.
        $relance = $this->tenterLaConsigne($partie, $type, $poste, $ordre);

        // Sans consigne pour le reprendre, l'ordre livré quitte l'atelier.
        if (!$relance['reprise']) {
            $ville->retirerOrdreDeFabrication($ordre);
        }

        return [...$messages, ...$relance['messages']];
    }

    /**
     * Relance l'atelier si une consigne le demande.
     *
     * Elle ne force rien : l'ordre passe par les mêmes vérifications qu'à la
     * main. Faute de matières, l'atelier s'arrête et **ne le dit qu'une fois**
     * (`ConsigneDeFabrication::signalerLAttente()`), puis retente à chaque
     * quinzaine en silence.
     *
     * `reprise` dit si l'ordre passé en réemploi est reparti : c'est lui qui
     * décide si l'atelier garde sa ligne ou la rend.
     *
     * @return array{messages: list<string>, reprise: bool}
     */
    private function tenterLaConsigne(GameSave $partie, TypeDeBatiment $type, int $poste = 1, ?OrdreDeFabrication $aReemployer = null): array
    {
        $ville = $partie->getVille();
        $consigne = $ville->consigneDeFabricationDe($type, $poste);

        if (null === $consigne) {
            return ['messages' => [], 'reprise' => false];
        }

        try {
            $this->engager($partie, $consigne->getRecette(), $consigne->getLots(), $poste, $aReemployer);
        } catch (FabricationImpossible $empechement) {
            return [
                'messages' => $consigne->signalerLAttente()
                    ? [\sprintf('%s s\'arrête : %s', $poste > 1 ? \sprintf('%s (poste %d)', $type->libelle(), $poste) : $type->libelle(), lcfirst($empechement->getMessage()))]
                    : [],
                'reprise' => false,
            ];
        }

        $attendait = $consigne->estEnAttenteDeMatieres();
        $consigne->reprendre();
        $nom = $poste > 1 ? \sprintf('%s (poste %d)', $type->libelle(), $poste) : $type->libelle();

        $messages = [\sprintf(
            '%s se remet à %s.',
            $nom,
            $consigne->getRecette()->libelle(),
        )];

        return [
            'messages' => $attendait
                ? [\sprintf('%s reprend son ouvrage.', $nom), ...$messages]
                : $messages,
            'reprise' => true,
        ];
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
            $supplementaire = $recette->deblocageSupplementaire();

            $empechement = null;

            if (null !== $supplementaire) {
                [$autre, $niveauExige] = $supplementaire;

                if (($ville->batimentDeType($autre)?->getNiveau() ?? 0) < $niveauExige) {
                    $empechement = \sprintf('Demande %s de niveau %d.', $autre->libelle(), $niveauExige);
                }
            }

            $empechement ??= [] === $manques ? null : \sprintf('Il vous manque %s.', implode(', ', $manques));

            $offre[] = [
                'recette' => $recette,
                'matieres' => self::enLibelles($matieres),
                'realisable' => null === $empechement,
                'empechement' => $empechement,
            ];
        }

        return $offre;
    }

    public static function lotsMaximum(int $niveau): int
    {
        return max(1, self::LOTS_PAR_NIVEAU * $niveau);
    }

    /**
     * Ce qu'un ordre de `$lots` lots consomme : **des matières, jamais du
     * deben** (voir `Recette::coutDunLot()`). La main-d'œuvre est déjà payée
     * par les salaires de la quinzaine ; la faire payer une seconde fois ici
     * interdisait de fabriquer à qui n'avait plus rien en caisse — c'est-à-dire
     * précisément à qui en avait besoin.
     *
     * @return array<string, int>
     */
    public static function matieresPour(Recette $recette, int $lots): array
    {
        $matieres = [];

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
