<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Déchiffrer une inscription (doc 10).
 *
 * **Une énigme ne bloque jamais** (décision de la joueuse) : se tromper ne
 * coûte ni ressource ni cycle, on relit et on recommence. Le coût d'une
 * inscription est le temps qu'on passe dessus, et rien d'autre — une énigme
 * qui punit est une énigme qu'on cesse de tenter, ce qui est le contraire de
 * l'objectif pédagogique.
 *
 * **Réussir apprend un signe de plus.** C'est la seconde voie d'enrichissement
 * de la clé annoncée par le doc 10, et elle referme la boucle du lot 7.0 : on
 * lit ce qu'on sait, et lire fait savoir davantage. C'est aussi ce qui permet
 * à une ville dont la Maison des scribes plafonne — le Delta — de continuer
 * d'apprendre.
 */
final readonly class Dechiffrage
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * L'inscription qu'on propose : la première que la ville sait lire et
     * n'a pas encore déchiffrée. Nulle quand il n'en reste aucune.
     */
    public function proposition(GameSave $partie): ?Inscription
    {
        // Ce que le roi attend passe avant le reste.
        $duFilRouge = FilRouge::inscriptionDeLActe($partie);

        if (null !== $duFilRouge && $duFilRouge->estLisiblePar($partie->getVille(), $partie->getCycle())) {
            return $duFilRouge;
        }

        foreach (Inscription::disponiblesPour($partie->getVille(), $partie->getCycle()) as $inscription) {
            if (FilRouge::inscriptionOuverte($partie, $inscription)) {
                return $inscription;
            }
        }

        return null;
    }

    /**
     * Vérifie une proposition de lecture.
     *
     * @param list<string> $ordre les clés des signes, dans l'ordre proposé
     *
     * @return array{juste: bool, apprend: ?SymboleHieroglyphique}
     *
     * @throws DechiffrageImpossible
     */
    public function verifier(GameSave $partie, Inscription $inscription, array $ordre): array
    {
        $ville = $partie->getVille();

        if (!$inscription->estLisiblePar($ville, $partie->getCycle())) {
            throw new DechiffrageImpossible('Vos scribes ne connaissent pas tous ces signes.');
        }

        if (\in_array($inscription, $ville->inscriptionsDechiffrees(), true)) {
            throw new DechiffrageImpossible('Cette inscription est déjà lue.');
        }

        // On ne lit pas la conclusion avant l'obstacle.
        if (!FilRouge::inscriptionOuverte($partie, $inscription)) {
            throw new DechiffrageImpossible('Ce n\'est pas le moment de lire cette pierre-là.');
        }

        $attendu = array_map(
            static fn (SymboleHieroglyphique $signe): string => $signe->value,
            $inscription->signes(),
        );

        if ($ordre !== $attendu) {
            return ['juste' => false, 'apprend' => null];
        }

        $ville->dechiffrer($inscription);

        // La récompense du doc 10 pour une énigme simple. Un signe plutôt
        // qu'une poignée de deben : c'est ce qui fait de la lecture sa propre
        // récompense, et ce qui permet d'apprendre encore quand le bâtiment
        // ne peut plus monter.
        $apprend = CleDeLecture::prochainSigne($ville, $partie->getCycle());

        if (null !== $apprend) {
            $ville->apprendreUnSymbole($apprend);
        }

        $this->entityManager->flush();

        return ['juste' => true, 'apprend' => $apprend];
    }
}
