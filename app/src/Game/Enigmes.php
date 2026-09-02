<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Répondre à une énigme courte (doc 10).
 *
 * **Une seule tentative par énigme.** Avec un droit de reprise illimité sur
 * quatre propositions, on essaie tout : il n'y aurait plus de question. C'est
 * la contrepartie de leur caractère facultatif — elles ne bloquent jamais rien,
 * donc elles peuvent se perdre.
 *
 * **L'explication tombe dans les deux cas.** C'est le vrai gain : la
 * récompense en deben passe, ce qu'on a appris reste. Une énigme ratée qui
 * n'expliquerait rien punirait deux fois, et n'enseignerait pas — ce qui est
 * l'objet même du doc 10.
 */
final readonly class Enigmes
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Enigme>
     */
    public function disponibles(GameSave $partie): array
    {
        return Enigme::disponiblesPour($partie->getVille());
    }

    /**
     * Les propositions à montrer. **L'Oraculaire en écarte une mauvaise**
     * (doc 03) : il ne donne pas la réponse, il resserre le doute — ce qui est
     * exactement ce qu'un oracle faisait. Son effet n'étant pas une
     * production, il passe par `chefSpecialise()`.
     *
     * L'écart se fait **à l'affichage seulement** : la bonne réponse reste la
     * bonne, et une réponse écartée soumise à la main reste recevable — elle
     * est simplement fausse.
     *
     * @return list<string>
     */
    public function propositionsMontrees(GameSave $partie, Enigme $enigme): array
    {
        $propositions = $enigme->propositions();

        if (!EffetDeChef::chefSpecialise($partie->getVille(), TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire, $partie->getCycle())) {
            return $propositions;
        }

        $ecartees = 0;
        $retenues = [];

        foreach ($propositions as $proposition) {
            if ($proposition !== $enigme->bonneReponse() && $ecartees < EffetDeChef::PROPOSITIONS_ECARTEES_PAR_LORACULAIRE) {
                ++$ecartees;

                continue;
            }

            $retenues[] = $proposition;
        }

        return $retenues;
    }

    /**
     * @return array{juste: bool, explication: string, recompense: int, renommee: int}
     *
     * @throws EnigmeImpossible
     */
    public function repondre(GameSave $partie, Enigme $enigme, string $reponse): array
    {
        $ville = $partie->getVille();

        if (!$ville->possede($enigme->lieu())) {
            throw new EnigmeImpossible(\sprintf('Il vous faut %s pour l\'entendre.', $enigme->lieu()->libelle()));
        }

        if (\in_array($enigme, $ville->enigmesTentees(), true)) {
            throw new EnigmeImpossible('Vous avez déjà donné votre réponse.');
        }

        if (!\in_array($reponse, $enigme->propositions(), true)) {
            throw new EnigmeImpossible('Ce n\'est pas une des réponses proposées.');
        }

        $juste = $reponse === $enigme->bonneReponse();
        $ville->tenterUneEnigme($enigme);
        $renommee = 0;

        if ($juste) {
            $ville->crediterRessources([Ressource::Deben->value => Enigme::RECOMPENSE_EN_DEBEN]);

            // Le doc 13 accorde un point par énigme résolue, que le jeu ne
            // donnait pas : une règle écrite et jamais appliquée.
            $renommee = $partie->getFamille()->crediterUneAffaireResolue(Enigme::RENOMMEE_POUR_UNE_RESOLUE);
        }

        $this->entityManager->flush();

        return [
            'juste' => $juste,
            'explication' => $enigme->explication(),
            'recompense' => $juste ? Enigme::RECOMPENSE_EN_DEBEN : 0,
            'renommee' => $renommee,
        ];
    }
}
