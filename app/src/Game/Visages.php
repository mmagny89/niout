<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Lignee;
use App\Entity\Npc;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Une personne qu'on a rencontrée peut se reconnaître d'une partie à l'autre (`docs/plan-pnj-palier-b.md` § 7).
 *
 * **Deux gestes, aux deux jalons de la lignée :**
 *  - `retenir()`, quand un jalon est franchi (`Lignees::encaisser`) : les marchands rivaux et les chefs partis que
 *    la partie a croisés entrent dans la mémoire de la famille, **douze au plus** ;
 *  - `figurer()`, au lancement d'une partie : **un** de ces visages (pas davantage) y est semé, par la graine. Il
 *    reparaîtra comme n'importe quelle personne connue — un rival à son tour, un chef parmi les candidats — mais
 *    **jamais avant `DELAI_AVANT_DE_PARAITRE` quinzaines** : le monde n'attend pas la famille à la porte.
 *
 * **Aucune rancune.** Ce qu'on retient, ce sont les accords passés (au plus trois) : un marchand payé revient
 * plus sensible à un accord, jamais plus dur. Un chef revient aussi aguerri que lorsqu'il est parti, pas davantage.
 */
final readonly class Visages
{
    /** Combien de quinzaines un visage retenu attend avant de pouvoir reparaître dans une partie neuve. **Valeur inventée.** */
    public const int DELAI_AVANT_DE_PARAITRE = 10;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Verse à la lignée les personnes que cette partie a fait connaître.
     */
    public function retenir(GameSave $partie, Lignee $lignee): void
    {
        $connus = $this->entityManager->getRepository(Npc::class)->findBy(['partie' => $partie], ['id' => 'ASC']);

        foreach ($connus as $npc) {
            // Un courtisan s'efface avec son règne et un chef de bande avec sa case : ni l'un ni l'autre ne
            // survit à la partie — leur identité tient à la carte ou au règne, qu'une autre partie n'a pas.
            if (!\in_array($npc->getRole(), [RoleDePnj::Rival, RoleDePnj::ChefVoyageur], true)) {
                continue;
            }

            $lignee->retenirUnVisage(
                $npc->getIdentite(),
                $npc->getRole()->value,
                $npc->getNom(),
                $npc->getCaractere()->value,
                $npc->getAccords(),
                RoleDePnj::ChefVoyageur === $npc->getRole() ? $this->profilDeVoyage($npc) : [],
            );
        }
    }

    /**
     * Sème, dans une partie neuve, **un** visage que la famille connaît — ou aucun. Déduit de la graine :
     * rejouable.
     */
    public function figurer(GameSave $partie, Lignee $lignee): ?Npc
    {
        $visages = $lignee->getVisagesConnus();

        if ([] === $visages) {
            return null;
        }

        $visage = $visages[self::entier($partie->getGraine(), 'visage') % \count($visages)];
        $role = RoleDePnj::tryFrom($visage['role']);
        $caractere = TraitDeCandidat::tryFrom($visage['caractere']);

        if (null === $role || null === $caractere) {
            return null;
        }

        $npc = new Npc($partie, $visage['identite'], $role, $visage['nom'], $caractere);
        // Un rival payé revient plus sensible à un accord ; rien d'autre ne se transmet.
        $npc->reconnaitre($visage['accords'], self::DELAI_AVANT_DE_PARAITRE);

        if (RoleDePnj::ChefVoyageur === $role) {
            $npc->definirLeProfil(['depart' => -ChefsVoyageurs::DELAI_DE_RETOUR, 'gagne' => 0, 'revenu' => false, 'destination' => ''] + $visage['profil']);
        }

        $npc->noter($partie->getCycle(), 'reconnu');
        $this->entityManager->persist($npc);

        return $npc;
    }

    /**
     * Ce qu'il faut garder d'un chef parti pour qu'il puisse se présenter à une annonce ailleurs : sa candidature
     * et son bâtiment. Sa destination ne suit pas — elle n'existe peut-être pas dans l'autre monde.
     *
     * @return array<string, mixed>
     */
    private function profilDeVoyage(Npc $npc): array
    {
        $profil = $npc->getProfil();

        return ['candidat' => $profil['candidat'] ?? [], 'batiment' => $profil['batiment'] ?? ''];
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
