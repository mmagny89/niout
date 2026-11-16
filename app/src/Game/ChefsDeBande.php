<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Un visage derrière le risque de pillage (`docs/plan-pnj-palier-b.md` § 5.3).
 *
 * **Les bandes ne bougent pas** : elles restent un attribut de case (`Bandits`), et la carte, les garanties de
 * départ et le butin décroissant n'y changent rien. Ce que B4 ajoute ne touche pas la carte :
 *
 *  - **un chef nommé** par bande, **déduit** de la graine et de la case — il ne se stocke pas ;
 *  - **une réputation** : combien de convois il a inquiétés. Elle naît à son premier pillage (un `Npc`, créé
 *    paresseusement) et se lit dans « Le monde » ;
 *  - **une troisième issue**, en plus de l'escorte (`Medjays`) et du combat (`Combat`) : **un droit de
 *    passage**. Des deben contre `REDUCTION_DU_DROIT` % du risque pendant `DUREE_DU_DROIT` quinzaines, au prix
 *    des butins qu'on leur épargne.
 *
 * Le droit **réduit le risque, il ne le supprime pas** (et ne se cumule pas avec lui-même) : pas de route qu'un
 * paiement unique rendrait sûre pour toujours, et la garnison garde sa valeur.
 */
final readonly class ChefsDeBande
{
    /** Ce que le droit retire du risque, en centièmes. **Valeur inventée** (`docs/plan-pnj-palier-b.md` § 11). */
    public const int REDUCTION_DU_DROIT = 40;

    /** Combien de quinzaines il vaut. **Valeur inventée.** */
    public const int DUREE_DU_DROIT = 6;

    /** Le prix, en multiple du butin que les bandes tireraient d'un combat perdu — « indexé sur le butin ». */
    public const int PRIX_EN_BUTINS = 2;

    /** Des noms de gens ordinaires attestés au Nouvel Empire. Aucun nom de personnage historique. */
    private const array NOMS = ['Seba', 'Nehesy', 'Mery', 'Sennefer', 'Bak', 'Iny', 'Khnoumhotep', 'Amenemopé'];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Le chef d'une case gardée. Pure : même graine, même case, même chef.
     *
     * @return array{identite: int, nom: string}
     */
    public function chefDe(GameSave $partie, Zone $zone): array
    {
        $identite = self::entier($partie->getGraine(), 'bande', $zone->getX(), $zone->getY());

        return ['identite' => $identite, 'nom' => self::NOMS[$identite % \count(self::NOMS)].' le chef de bande'];
    }

    /**
     * Les chefs des bandes encore tenues, avec ce qu'on sait de leur réputation.
     *
     * @return list<array{nom: string, inquietes: int, x: int, y: int}>
     */
    public function lesBandes(GameSave $partie): array
    {
        $bandes = [];

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estGardee()) {
                continue;
            }

            $chef = $this->chefDe($partie, $zone);
            $npc = $this->entityManager->getRepository(Npc::class)->findOneBy(['partie' => $partie, 'role' => RoleDePnj::ChefDeBande, 'identite' => $chef['identite']]);

            $bandes[] = ['nom' => $chef['nom'], 'inquietes' => (int) ($npc?->getProfil()['convois'] ?? 0), 'x' => $zone->getX(), 'y' => $zone->getY()];
        }

        return $bandes;
    }

    /**
     * Un convoi vient d'être pillé : à qui l'attribuer ? À l'une des bandes encore tenues, choisie par la graine
     * (la quinzaine et le convoi) — jamais au hasard global. Renvoie le nom du chef, `null` si nulle bande ne
     * tient plus de case (le risque est alors nul, et on n'arrive pas ici).
     */
    public function attribuerUnPillage(GameSave $partie, int $convoi): ?string
    {
        $gardees = [];

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->estGardee()) {
                $gardees[] = $zone;
            }
        }

        if ([] === $gardees) {
            return null;
        }

        $cycle = $partie->getCycle();
        $zone = $gardees[self::entier($partie->getGraine(), 'pillage', $cycle, $convoi) % \count($gardees)];
        $chef = $this->chefDe($partie, $zone);

        $depot = $this->entityManager->getRepository(Npc::class);
        $npc = $depot->trouverOuEnAttente($partie, RoleDePnj::ChefDeBande, $chef['identite']);

        if (null === $npc) {
            $npc = new Npc($partie, $chef['identite'], RoleDePnj::ChefDeBande, $chef['nom'], TraitDeCandidat::Bagarreur);
            $this->entityManager->persist($npc);
        }

        $profil = $npc->getProfil();
        $npc->definirLeProfil(['convois' => (int) ($profil['convois'] ?? 0) + 1] + $profil);
        $npc->noter($cycle, 'pille');

        return $chef['nom'];
    }

    public function droitActif(GameSave $partie): bool
    {
        return $partie->getDroitDePassageJusqua() > $partie->getCycle();
    }

    /**
     * Ce que coûte le droit : le double du butin que chaque bande tenue tirerait d'une défaite. Une région sans
     * bande n'a rien à payer — et le droit n'a pas d'objet.
     */
    public function prixDuDroit(GameSave $partie): int
    {
        $prix = 0;

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->estGardee()) {
                $prix += self::PRIX_EN_BUTINS * intdiv(Bandits::defenseDe($partie->getVille(), $zone) * Combat::BUTIN_POUR_CENT_DE_LA_DEFENSE, 100);
            }
        }

        return $prix;
    }

    /**
     * On paie : six quinzaines, une réduction du risque. Rien n'est débité avant d'avoir vérifié que le droit
     * a un objet et qu'on peut le payer.
     *
     * @throws CommerceImpossible
     */
    public function payerLeDroit(GameSave $partie): string
    {
        if (0 === Bandits::compterLesZonesGardees($partie->getVille())) {
            throw new CommerceImpossible('Aucune bande ne guette vos routes : il n\'y a rien à payer.');
        }

        if ($this->droitActif($partie)) {
            throw new CommerceImpossible(\sprintf('Votre droit de passage court encore jusqu\'à la quinzaine %d.', $partie->getDroitDePassageJusqua() - 1));
        }

        $prix = $this->prixDuDroit($partie);

        if (!$partie->getVille()->debiterRessources([Ressource::Deben->value => $prix])) {
            throw new CommerceImpossible(\sprintf('Il vous faut %d deben pour acheter le passage.', $prix));
        }

        $partie->acheterLeDroitDePassage($partie->getCycle() + self::DUREE_DU_DROIT);

        return \sprintf('Vous versez %d deben aux bandes : vos convois risquent %d %% de moins pendant %d quinzaines.', $prix, self::REDUCTION_DU_DROIT, self::DUREE_DU_DROIT);
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
