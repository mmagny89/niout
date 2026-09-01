<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\QueteDeChantier;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Les requêtes du pharaon pour ses chantiers (doc 09).
 *
 * **Jamais obligatoires**, et c'est ce qui les rend intéressantes : elles ne
 * comptent pas dans la réussite de la mission, mais elles font gagner de la
 * renommée et de la faveur. Refuser coûte deux points de renommée, rien de
 * plus — le joueur reste libre de sa stratégie.
 *
 * **Laisser filer le délai revient à refuser.** Sans cela, attendre serait
 * toujours meilleur que refuser, et le délai ne voudrait rien dire.
 *
 * Chaque quête cite un monument **réellement bâti** par le pharaon
 * commanditaire, avec ce qu'on en sait (`ChantierRoyal`).
 */
final readonly class QuetesDeChantier
{
    /**
     * Une requête tous les quatre cycles, dit le doc 09 — deux mois de jeu.
     */
    public const int CYCLES_ENTRE_DEUX = 4;

    /**
     * Ce que le pharaon réclame, et le temps qu'il laisse pour l'apporter.
     */
    public const int QUANTITE_MINIMALE = 20;
    public const int QUANTITE_MAXIMALE = 50;
    public const int DELAI_EN_QUINZAINES = 6;

    public const int RENOMMEE_GAGNEE = 5;
    public const int RENOMMEE_PERDUE = 2;
    public const int FAVEUR_GAGNEE = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionCatalogue $missions,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();

        if (null !== $quete) {
            if (!$quete->avancerDUnCycle()) {
                return [];
            }

            $ville->retirerLaQueteDeChantier();
            $partie->getFamille()->ajusterRenommee(-self::RENOMMEE_PERDUE);
            $this->entityManager->remove($quete);

            return [\sprintf(
                'Le délai est passé : la pierre pour %s n\'est jamais partie. On le remarque.',
                $quete->getChantier()->libelle(),
            )];
        }

        if (0 !== $partie->getCycle() % self::CYCLES_ENTRE_DEUX) {
            return [];
        }

        return $this->reclamer($partie);
    }

    /**
     * Livre ce que le pharaon demandait.
     *
     * @throws QueteImpossible
     */
    public function livrer(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();

        if (null === $quete) {
            throw new QueteImpossible('Le pharaon ne vous demande rien.');
        }

        if (!$ville->debiterRessources([$quete->getRessource()->value => $quete->getQuantite()])) {
            throw new QueteImpossible(\sprintf('Il vous faut %d %s pour honorer cette demande.', $quete->getQuantite(), $quete->getRessource()->libelle()));
        }

        $partie->getFamille()->ajusterRenommee(self::RENOMMEE_GAGNEE);
        $divinite = $quete->getChantier()->divinite();

        if (null !== $divinite) {
            // Le monument honore un dieu : le servir vous vaut sa faveur, dans
            // les bornes que le Temple autorise.
            $faveur = $ville->suivreLaFaveurDe($divinite);
            $faveur->ajuster(min(
                self::FAVEUR_GAGNEE,
                max(0, Temple::plafondDeFaveur($ville) - $faveur->getFaveur()),
            ));
        }

        $ville->retirerLaQueteDeChantier();
        $this->entityManager->remove($quete);
        $this->entityManager->flush();

        return \sprintf(
            'Votre chargement part pour %s. %s',
            $quete->getChantier()->libelle(),
            $quete->getChantier()->ceQuOnEnSait(),
        );
    }

    /**
     * Refuse la demande : deux points de renommée, et rien d'autre.
     *
     * @throws QueteImpossible
     */
    public function refuser(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();

        if (null === $quete) {
            throw new QueteImpossible('Le pharaon ne vous demande rien.');
        }

        $ville->retirerLaQueteDeChantier();
        $partie->getFamille()->ajusterRenommee(-self::RENOMMEE_PERDUE);
        $this->entityManager->remove($quete);
        $this->entityManager->flush();

        return 'Vous déclinez. Le chantier se passera de vous, et l\'on s\'en souviendra un peu.';
    }

    /**
     * @return list<string>
     */
    private function reclamer(GameSave $partie): array
    {
        $numero = $partie->getMission();

        if (!$partie->estCampagne() || null === $numero) {
            return [];
        }

        $mission = $this->missions->get($numero);
        $chantier = ChantierRoyal::pour($mission->pharaon);

        if (null === $chantier) {
            return [];
        }

        // Il réclame ce que la région porte : envoyer chercher au loin ce
        // qu'on a sous les pieds n'aurait pas de sens, et rendrait la quête
        // impossible dans la moitié des missions.
        $possibles = $mission->geographie->ressourcesDeZone;

        if ([] === $possibles) {
            return [];
        }

        $ressource = $possibles[$this->hasard->getInt(0, \count($possibles) - 1)];
        $quantite = $this->hasard->getInt(self::QUANTITE_MINIMALE, self::QUANTITE_MAXIMALE);

        $quete = new QueteDeChantier($partie->getVille(), $chantier, $ressource, $quantite, self::DELAI_EN_QUINZAINES);
        $partie->getVille()->recevoirUneQuete($quete);
        $this->entityManager->persist($quete);

        return [\sprintf(
            '%s réclame %d %s pour %s. Vous avez %d quinzaines.',
            $mission->pharaon,
            $quantite,
            $ressource->libelle(),
            $chantier->libelle(),
            self::DELAI_EN_QUINZAINES,
        )];
    }
}
