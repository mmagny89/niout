<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Random\Randomizer;

/**
 * Le bilan démographique d'une année de jeu (décision de la joueuse).
 *
 * Les habitants ne sont pas suivis un par un : la ville tient trois nombres —
 * enfants, actifs, anciens — et c'est **une fois l'an**, non à chaque
 * quinzaine, qu'ils bougent. Des enfants entrent dans la vie active, des
 * actifs passent la main, et la mort prend sa part.
 *
 * **Chaque personne est tirée séparément**, plutôt que d'appliquer un
 * pourcentage à un total. C'est ce qui permet de rester en nombres entiers
 * sans traîner de reliquat d'une année à l'autre — un taux de 3 % sur douze
 * actifs ne donnerait sinon jamais rien. Et la variance qui en résulte est
 * juste : certaines années sont plus dures que d'autres.
 *
 * Deux choses peuvent au contraire faire croître la ville, et **toutes deux
 * s'arrêtent net quand les maisons sont pleines** : les naissances, et la
 * migration spontanée que le doc 13 accorde aux familles respectées. Ni l'une
 * ni l'autre ne remplace l'appel volontaire d'habitants — elles maintiennent,
 * elles n'agrandissent qu'à la marge.
 *
 * Ne persiste rien, comme les autres résolutions de cycle : `PassageDeCycle`
 * réunit tout en une seule écriture.
 */
final readonly class Demographie
{
    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Le bilan d'une année écoulée. C'est `PassageDeCycle` qui décide du
     * moment — à la bascule d'année, avec la crue —, pas ce service : lui
     * demander de vérifier la date le ferait tomber dès le premier cycle
     * d'une partie, où la ville vient tout juste d'arriver.
     *
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function bilanDeLAnnee(GameSave $partie): array
    {
        $ville = $partie->getVille();

        if (0 === $ville->population()) {
            return [];
        }

        // Tout se calcule sur l'état du début d'année, puis s'applique d'un
        // bloc : sans ça, un enfant devenu actif pourrait vieillir dans la
        // même année, et mourir deux fois.
        $enfantsDevenusActifs = $this->tirer($ville->getEnfants(), Population::CHANCE_ENFANT_DEVIENT_ACTIF);
        $actifsDevenusAnciens = $this->tirer($ville->getActifs(), Population::CHANCE_ACTIF_DEVIENT_ANCIEN);

        $decesEnfants = $this->tirer($ville->getEnfants() - $enfantsDevenusActifs, Population::CHANCE_DECES_ENFANT);
        $decesActifs = $this->tirer($ville->getActifs() - $actifsDevenusAnciens, Population::CHANCE_DECES_ACTIF);
        $decesAnciens = $this->tirer($ville->getAnciens(), Population::CHANCE_DECES_ANCIEN);

        $ville->appliquerLeBilanDeLAnnee(
            $enfantsDevenusActifs,
            $actifsDevenusAnciens,
            $decesEnfants,
            $decesActifs,
            $decesAnciens,
        );

        // Naissances et migration se calculent après le bilan, sur une ville
        // dont on connaît enfin la place restante : une maison libérée par un
        // décès peut accueillir dans la même année.
        $naissances = $this->naitre($partie);
        $arrivees = $this->migrerSpontanement($partie);

        return [
            ...$this->raconter($enfantsDevenusActifs, $actifsDevenusAnciens, $decesEnfants + $decesActifs + $decesAnciens),
            ...$naissances,
            ...$arrivees,
        ];
    }

    /**
     * Les naissances de l'année — jamais au-delà de ce que la ville peut
     * loger.
     *
     * @return list<string>
     */
    private function naitre(GameSave $partie): array
    {
        $ville = $partie->getVille();

        if ($ville->manqueDeLogements()) {
            return [];
        }

        $naissances = $this->tirer($ville->getActifs(), Population::CHANCE_NAISSANCE_PAR_ACTIF);

        if (0 === $naissances) {
            return [];
        }

        $ville->accueillir(0, $naissances, 0);

        return [1 === $naissances
            ? 'Un enfant est né cette année.'
            : \sprintf('%d enfants sont nés cette année.', $naissances),
        ];
    }

    /**
     * La migration spontanée du doc 13 : à partir du palier « Respectée », des
     * maisonnées s'installent sans qu'on les appelle ni qu'on les paie.
     *
     * @return list<string>
     */
    private function migrerSpontanement(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $palier = $partie->getFamille()->palier();

        if ($ville->manqueDeLogements() || $this->hasard->getInt(1, 100) > $palier->chanceDeMigrationSpontanee()) {
            return [];
        }

        $maisonnee = Population::maisonneeQuiArrive($this->hasard);
        $ville->accueillir($maisonnee['actifs'], $maisonnee['inactifs'], 0);

        return [\sprintf(
            'Votre renommée a fait venir une maisonnée : %d bras et %d bouches de plus.',
            $maisonnee['actifs'],
            $maisonnee['inactifs'],
        )];
    }

    /**
     * Combien, sur `$combien` personnes, tombent dans un événement qui a
     * `$chance` % de survenir pour chacune.
     */
    private function tirer(int $combien, int $chance): int
    {
        $touches = 0;

        for ($i = 0; $i < max(0, $combien); ++$i) {
            if ($this->hasard->getInt(1, 100) <= $chance) {
                ++$touches;
            }
        }

        return $touches;
    }

    /**
     * @return list<string>
     */
    private function raconter(int $majeurs, int $vieillis, int $morts): array
    {
        $evenements = [];

        if ($majeurs > 0) {
            $evenements[] = 1 === $majeurs
                ? 'Un enfant de la ville est en âge de travailler.'
                : \sprintf('%d enfants de la ville sont en âge de travailler.', $majeurs);
        }

        if ($vieillis > 0) {
            $evenements[] = 1 === $vieillis
                ? 'Un habitant s\'est retiré du travail.'
                : \sprintf('%d habitants se sont retirés du travail.', $vieillis);
        }

        if ($morts > 0) {
            $evenements[] = 1 === $morts
                ? 'La ville a enterré un des siens cette année.'
                : \sprintf('La ville a enterré %d des siens cette année.', $morts);
        }

        return $evenements;
    }
}
