<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\City;
use App\Entity\Employee;
use App\Game\Candidat;
use App\Game\Effectifs;
use App\Game\EffetDeChef;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EffetDeChef::class)]
final class EffetDeChefTest extends TestCase
{
    /**
     * L'invariant qui rend l'embauche jouable : **un mauvais chef reste
     * meilleur que pas de chef**. Sans lui, embaucher au hasard serait un
     * risque de faire pire que rien, et le joueur n'oserait plus.
     */
    public function testUnMauvaisChefNestJamaisPunitif(): void
    {
        $pire = EffetDeChef::facteurDeCompetence(20);

        self::assertGreaterThanOrEqual(
            Effectifs::RENDEMENT_PLEIN - 5,
            $pire,
            'Le pire des chefs doit rester quasi neutre.',
        );
    }

    /**
     * Payer plus cher doit rapporter : la compétence croît strictement.
     */
    public function testLaCompetenceSePaieEnProduction(): void
    {
        $precedent = 0;

        foreach ([20, 40, 60, 80, 100] as $competence) {
            $facteur = EffetDeChef::facteurDeCompetence($competence);

            self::assertGreaterThan($precedent, $facteur);
            $precedent = $facteur;
        }

        self::assertSame(130, $precedent, 'Un chef parfait vaut un tiers de production de plus.');
    }

    /**
     * Le doc 03 chiffre le Pêcheur à +20 % et le Vendeur à +10 %.
     */
    public function testLesSpecialitesDuDocumentPortentLeursValeurs(): void
    {
        self::assertSame(20, EffetDeChef::bonusDeSpecialite(SpecialiteDeChef::PortPecheur));
        self::assertSame(10, EffetDeChef::bonusDeSpecialite(SpecialiteDeChef::MarcheVendeur));
        self::assertSame(0, EffetDeChef::bonusDeSpecialite(null));
    }

    /**
     * Les spécialités des bâtiments qui ne produisent pas encore ne doivent
     * rien ajouter : les annoncer actives mentirait au joueur.
     */
    public function testUneSpecialiteEndormieNajouteRien(): void
    {
        foreach (SpecialiteDeChef::cases() as $specialite) {
            if (!$specialite->agitDeja()) {
                self::assertSame(
                    0,
                    EffetDeChef::bonusDeSpecialite($specialite),
                    \sprintf('%s dort : elle ne doit rien ajouter.', $specialite->libelle()),
                );
            }
        }
    }

    /**
     * Plusieurs chefs se moyennent plutôt que de s'additionner : les cumuler
     * ferait du niveau du bâtiment un multiplicateur déguisé, alors qu'il a
     * déjà son propre effet.
     */
    public function testPlusieursChefsSeMoyennent(): void
    {
        $ville = new City('Avaris', 0, 3);
        $ville->accueillir(20, 0, 0);
        $ville->ajouterBatiment(new \App\Entity\Building($ville, TypeDeBatiment::Grenier, 4));

        $this->engager($ville, competence: 20);
        $unSeul = EffetDeChef::facteurDesChefs($ville, TypeDeBatiment::Grenier, 1);

        $this->engager($ville, competence: 100);
        $lesDeux = EffetDeChef::facteurDesChefs($ville, TypeDeBatiment::Grenier, 1);

        self::assertSame(
            intdiv(EffetDeChef::facteurDeCompetence(20) + EffetDeChef::facteurDeCompetence(100), 2),
            $lesDeux,
        );
        self::assertGreaterThan($unSeul, $lesDeux);
    }

    /**
     * Sans chef, le facteur est neutre : c'est le seul rendement d'effectif
     * qui pèse. Un chef n'introduit jamais un multiplicateur de plus, il
     * module celui qui existe.
     */
    public function testSansChefLeFacteurEstNeutre(): void
    {
        $ville = new City('Avaris', 0, 3);

        self::assertSame(
            Effectifs::RENDEMENT_PLEIN,
            EffetDeChef::facteurDesChefs($ville, TypeDeBatiment::Grenier, 1),
        );
    }

    /**
     * Le calibrage attendu par le plan : le chef du Marché **double** les prix
     * de vente. Un Marché désert écoule au plancher de 50 %, un Marché tenu au
     * plein tarif et au-delà.
     */
    public function testUnChefDeMarcheDoubleLesPrix(): void
    {
        $ville = new City('Thèbes', 0, 3);
        $ville->accueillir(20, 0, 0);
        $ville->ajouterBatiment(new \App\Entity\Building($ville, TypeDeBatiment::Marche));

        $desert = EffetDeChef::qualiteDeDirection($ville, TypeDeBatiment::Marche, 1);
        self::assertSame(Effectifs::RENDEMENT_PLANCHER, $desert);

        $this->engager($ville, competence: 60, type: TypeDeBatiment::Marche, specialite: SpecialiteDeChef::MarcheVendeur);
        $tenu = EffetDeChef::qualiteDeDirection($ville, TypeDeBatiment::Marche, 1);

        self::assertGreaterThanOrEqual(2 * $desert, $tenu, 'Le chef doit au moins doubler ce que vaut le Marché.');
    }

    private function engager(
        City $ville,
        int $competence,
        TypeDeBatiment $type = TypeDeBatiment::Grenier,
        ?SpecialiteDeChef $specialite = null,
    ): void {
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: $competence,
                salaire: 8,
                ancienneteProbable: 20,
                traits: [],
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            1,
        ));
    }
}
