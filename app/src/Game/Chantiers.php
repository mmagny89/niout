<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lancement des chantiers et passage des cycles.
 *
 * C'est ici que bat le cœur du jeu : rien n'avance sans que le joueur ne
 * déclenche un cycle, et un cycle déclenché fait avancer tout ce qui est en
 * cours d'un coup.
 */
final readonly class Chantiers
{
    public function __construct(
        private CatalogueDeLaVille $catalogue,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Engage les travaux : débite les ressources et ouvre le chantier.
     *
     * @throws ChantierImpossible
     */
    public function lancer(GameSave $partie, TypeDeBatiment $type): Chantier
    {
        $ville = $partie->getVille();

        if ($ville->aUnChantierPour($type)) {
            throw new ChantierImpossible(\sprintf('Un chantier est déjà en cours sur le %s.', $type->libelle()));
        }

        $offre = $this->offrePour($partie, $type);

        if (!$offre->estRealisable()) {
            throw new ChantierImpossible($offre->empechement ?? 'Ce chantier n\'est pas possible.');
        }

        $cout = $offre->cout;
        \assert(null !== $cout);

        if (!$ville->payer($cout)) {
            throw new ChantierImpossible('Vos réserves ne suffisent plus.');
        }

        $niveauVise = null !== $offre->existant ? $offre->existant->getNiveau() + 1 : 1;
        $chantier = new Chantier($ville, $type, $niveauVise);
        $ville->ajouterChantier($chantier);

        $this->entityManager->flush();

        return $chantier;
    }

    /**
     * Fait avancer les chantiers d'un cycle. N'incrémente pas le compteur de
     * cycles et ne persiste rien : PassageDeCycle s'en charge, pour que tout
     * ce qui se résout dans une même quinzaine le fasse d'un bloc.
     *
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie, ?Saison $saison): array
    {
        $evenements = [];

        foreach ($partie->getVille()->getChantiers() as $chantier) {
            $chantier->avancerDUnCycle($saison);

            if ($chantier->estAcheve()) {
                $evenements[] = $this->achever($chantier);
            }
        }

        return $evenements;
    }

    /**
     * Un chantier achevé donne un bâtiment neuf, ou un niveau de plus.
     */
    private function achever(Chantier $chantier): string
    {
        $ville = $chantier->getVille();
        $type = $chantier->getType();
        $existant = $ville->batimentDeType($type);

        if (null !== $existant) {
            $existant->monterDUnNiveau();
            $message = \sprintf('Le %s atteint le niveau %d.', $type->libelle(), $existant->getNiveau());
        } else {
            $ville->ajouterBatiment(new Building($ville, $type, $chantier->getNiveauVise()));
            $message = \sprintf('Le %s est achevé.', $type->libelle());
        }

        $ville->retirerChantier($chantier);
        $this->entityManager->remove($chantier);

        return $message;
    }

    private function offrePour(GameSave $partie, TypeDeBatiment $type): OffreDeConstruction
    {
        foreach ($this->catalogue->pour($partie->getVille()) as $offre) {
            if ($offre->type === $type) {
                return $offre;
            }
        }

        throw new ChantierImpossible(\sprintf('Le %s ne peut pas être bâti ici.', $type->libelle()));
    }
}
