<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\Medjay;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lever et tenir une troupe (doc 03, doc 01).
 *
 * **Le frein est double, et les deux comptent.** L'effectif est borné par la
 * Caserne — un bâtiment tient une garnison, pas une armée — et l'entretien
 * rejoint la masse salariale de la ville : une troupe qu'on ne peut plus payer
 * mécontente la ville comme des chefs impayés. C'est ce qui empêche de lever
 * dix archers dès le quatrième niveau et de ne plus jamais y penser.
 */
final readonly class Medjays
{
    /**
     * L'effectif qu'une Caserne tient : `3 + 2 × niveau` (doc 01). Cinq au
     * premier niveau, vingt-et-un au neuvième.
     */
    public const int EFFECTIF_DE_BASE = 3;
    public const int EFFECTIF_PAR_NIVEAU = 2;

    /**
     * Les niveaux de **Résidence familiale** qui ouvrent chacun un emplacement
     * de plus (doc 01, lot 11.6).
     *
     * Le doc 01 promettait ces emplacements sans les chiffrer, alors qu'il
     * chiffrait l'effectif de la Caserne : le doublon a été tranché au 11.0, et
     * **les deux s'ajoutent**. La Caserne décide de l'essentiel — trois hommes
     * au plus viennent de la Résidence, ce qui ne dérègle aucun calibrage — et
     * la Résidence cesse d'être un bâtiment qui ne fait rien.
     *
     * @var list<int>
     */
    public const array PALIERS_DE_RESIDENCE = [1, 3, 5];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Combien d'hommes la ville peut tenir. Zéro sans Caserne : on ne loge pas
     * une troupe dans une ville qui n'a pas de quoi la caserner.
     */
    public function effectifMaximum(City $ville): int
    {
        $niveau = $ville->batimentDeType(TypeDeBatiment::Caserne)?->getNiveau() ?? 0;

        // **Sans Caserne, aucun homme** — quelle que soit la Résidence : on ne
        // loge pas une troupe dans une ville qui n'a pas de quoi la caserner,
        // et la Résidence ajoute des places, elle n'en crée pas de nulle part.
        if ($niveau < 1) {
            return 0;
        }

        return self::EFFECTIF_DE_BASE
            + self::EFFECTIF_PAR_NIVEAU * $niveau
            + self::emplacementsDeLaResidence($ville);
    }

    /**
     * Ce que la Résidence familiale ajoute à l'effectif (doc 01, lot 11.6) :
     * un homme par palier atteint.
     */
    public static function emplacementsDeLaResidence(City $ville): int
    {
        $niveau = $ville->batimentDeType(TypeDeBatiment::ResidenceFamiliale)?->getNiveau() ?? 0;

        return \count(array_filter(
            self::PALIERS_DE_RESIDENCE,
            static fn (int $palier): bool => $niveau >= $palier,
        ));
    }

    /**
     * Lève un homme, et le fait payer.
     *
     * @throws MedjayImpossible
     */
    public function lever(GameSave $partie, SpecialisationMedjay $specialisation): Medjay
    {
        $ville = $partie->getVille();
        $niveau = $ville->batimentDeType(TypeDeBatiment::Caserne)?->getNiveau() ?? 0;

        if ($niveau < 1) {
            throw new MedjayImpossible('Il vous faut une Caserne pour lever des Medjaÿ.');
        }

        if ($niveau < $specialisation->niveauDeCaserneRequis()) {
            throw new MedjayImpossible(\sprintf('Former un %s demande une Caserne de niveau %d ; la vôtre en est au %d.', mb_strtolower($specialisation->libelle()), $specialisation->niveauDeCaserneRequis(), $niveau));
        }

        if ($ville->getMedjays()->count() >= $this->effectifMaximum($ville)) {
            throw new MedjayImpossible(\sprintf('Votre Caserne ne tient que %d hommes. Montez-la d\'un niveau pour en loger davantage.', $this->effectifMaximum($ville)));
        }

        $cout = $specialisation->coutDeRecrutement();

        if (!$ville->debiterRessources([Ressource::Deben->value => $cout])) {
            throw new MedjayImpossible(\sprintf('Lever un %s demande %d deben ; il vous en manque %d.', mb_strtolower($specialisation->libelle()), $cout, $cout - $ville->getDeben()));
        }

        $medjay = new Medjay($ville, $specialisation);
        $ville->leverUnMedjay($medjay);

        $this->entityManager->persist($medjay);
        $this->entityManager->flush();

        return $medjay;
    }

    /**
     * Arme un homme d'une arme du stock (doc 03, lot 10.3).
     *
     * **Une arme, un homme, une fois.** Elle ne se consomme pas au combat
     * (arbitrage 10.0) : ce qu'on dépense ici, c'est la pièce elle-même, prise
     * au stock comme n'importe quelle marchandise. Réarmer un vétéran après
     * avoir monté la Forge en coûte une seconde — c'est ce qui fait du niveau
     * de Forge une décision plutôt qu'un compteur.
     *
     * @throws MedjayImpossible
     */
    public function armer(GameSave $partie, Medjay $medjay): int
    {
        $ville = $partie->getVille();

        if ($medjay->getVille() !== $ville) {
            throw new MedjayImpossible('Cet homme n\'est pas des vôtres.');
        }

        $qualite = Equipement::qualiteForgeePar($ville);

        if ($medjay->getQualiteDeLequipement() >= $qualite) {
            throw new MedjayImpossible('Il porte déjà ce que votre Forge sait faire de mieux.');
        }

        if (!$ville->debiterRessources([Ressource::Armes->value => 1])) {
            throw new MedjayImpossible('Vous n\'avez aucune arme en réserve. La Forge en fabrique à partir du cuivre.');
        }

        $medjay->recevoirUneArme($qualite);
        $this->entityManager->flush();

        return $qualite;
    }

    /**
     * Ce que la troupe coûte par quinzaine. Les blessés sont payés comme les
     * autres : on ne renvoie pas un homme parce qu'il s'est fait blesser à son
     * service.
     */
    public function entretienParQuinzaine(City $ville): int
    {
        $du = 0;

        foreach ($ville->getMedjays() as $medjay) {
            $du += $medjay->getSpecialisation()->entretienParQuinzaine();
        }

        return $du;
    }

    /**
     * Les hommes en état de partir.
     *
     * @return list<Medjay>
     */
    public function disponibles(GameSave $partie): array
    {
        $prets = [];

        foreach ($partie->getVille()->getMedjays() as $medjay) {
            if ($medjay->estDisponible($partie->getCycle())) {
                $prets[] = $medjay;
            }
        }

        return $prets;
    }

    /**
     * Ce que l'écran de la Caserne montre : chaque spécialisation, ce qu'elle
     * vaut, et ce qui empêche de la lever — dit **avant** la tentative, jamais
     * découvert par un refus.
     *
     * @return list<array{specialisation: SpecialisationMedjay, ouverte: bool, empechement: ?string}>
     */
    public function offreDeLaCaserne(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $niveau = $ville->batimentDeType(TypeDeBatiment::Caserne)?->getNiveau() ?? 0;
        $offre = [];

        foreach (SpecialisationMedjay::cases() as $specialisation) {
            $empechement = match (true) {
                $niveau < $specialisation->niveauDeCaserneRequis() => \sprintf(
                    'Caserne de niveau %d requise.',
                    $specialisation->niveauDeCaserneRequis(),
                ),
                $ville->getMedjays()->count() >= $this->effectifMaximum($ville) => 'Votre Caserne est pleine.',
                $ville->getDeben() < $specialisation->coutDeRecrutement() => \sprintf(
                    'Il vous manque %d deben.',
                    $specialisation->coutDeRecrutement() - $ville->getDeben(),
                ),
                default => null,
            };

            $offre[] = [
                'specialisation' => $specialisation,
                'ouverte' => null === $empechement,
                'empechement' => $empechement,
            ];
        }

        return $offre;
    }
}
