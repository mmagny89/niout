<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Npc;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Un visage derrière les requêtes du pharaon (`docs/plan-pnj-palier-b.md` § 5.4).
 *
 * Les requêtes (`QuetesDeChantier`) étaient anonymes. Elles ont maintenant **un courtisan** — un **titre**, jamais
 * un nom : le vizir, le scribe royal. **Aucun nom de personnage historique n'est écrit** : l'existence, le règne
 * et la fonction d'un vizir ou d'un général se vérifient avant d'être imprimés, jamais de mémoire (règle des
 * hiéroglyphes étendue aux personnes). Les titres, eux, sont des fonctions générales de la cour, attestées
 * comme telles.
 *
 * Il a **une humeur**, de −`HUMEUR_MAX` à +`HUMEUR_MAX` : elle monte quand la famille honore une requête, baisse
 * quand elle la refuse ou laisse filer le délai. Elle **module** deux choses, dans des bornes :
 *  - **le présent** que le palais renvoie (`+2 points de pourcentage par point d'humeur`, de 50 à 70 %) ;
 *  - **le ton** du message.
 *
 * **Jamais le caractère facultatif d'une requête**, ni sa fréquence, ni son coût : un courtisan impatient ne
 * punit rien, il récompense un peu moins.
 *
 * Il **accompagne la succession** : un nouveau règne, ou une nouvelle mission, amène **un autre courtisan**,
 * déduit de la graine ; l'ancien s'efface — c'est ce qui donne à un changement de règne un sentiment au lieu
 * d'un changement de nom. Créé **paresseusement**, à la première requête.
 */
final readonly class Cour
{
    public const int HUMEUR_MAX = 5;

    /** Ce qu'un point d'humeur change au présent, en points de pourcentage. **Valeur inventée.** */
    public const int PRESENT_PAR_POINT_D_HUMEUR = 2;

    /** Les fonctions de la cour, comme titres seulement. */
    private const array TITRES = [
        'le vizir',
        'l\'intendant du palais',
        'le scribe royal',
        'le chef des greniers du roi',
        'le directeur des travaux du roi',
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Le courtisan en poste pour ce règne (Aventure) ou cette mission (Campagne), déduit de la graine.
     * `$rang` est le rang du règne, ou le numéro de la mission.
     *
     * @return array{identite: int, titre: string}
     */
    public static function enPoste(GameSave $partie, int $rang): array
    {
        $identite = self::entier($partie->getGraine(), 'cour', $rang);

        return ['identite' => $identite, 'titre' => self::TITRES[$identite % \count(self::TITRES)]];
    }

    /**
     * Sa fiche, créée si c'est la première fois qu'il parle. Les courtisans qui l'ont précédé s'effacent.
     */
    public function obtenir(GameSave $partie, int $rang): Npc
    {
        $poste = self::enPoste($partie, $rang);
        $depot = $this->entityManager->getRepository(Npc::class);
        $npc = $depot->trouverOuEnAttente($partie, RoleDePnj::Courtisan, $poste['identite']);

        if (null !== $npc) {
            return $npc;
        }

        foreach ($depot->findBy(['partie' => $partie, 'role' => RoleDePnj::Courtisan]) as $ancien) {
            if (true !== ($ancien->getProfil()['efface'] ?? false)) {
                $ancien->definirLeProfil(['efface' => true] + $ancien->getProfil());
                $ancien->noter($partie->getCycle(), 's_efface');
            }
        }

        $npc = new Npc($partie, $poste['identite'], RoleDePnj::Courtisan, ucfirst($poste['titre']), TraitDeCandidat::Ambitieux);
        $npc->definirLeProfil(['humeur' => 0, 'rang' => $rang, 'efface' => false]);
        $npc->noter($partie->getCycle(), 'paraît');
        $this->entityManager->persist($npc);

        return $npc;
    }

    public static function humeurDe(Npc $courtisan): int
    {
        return max(-self::HUMEUR_MAX, min(self::HUMEUR_MAX, (int) ($courtisan->getProfil()['humeur'] ?? 0)));
    }

    /**
     * Ce que le palais renvoie en retour d'une requête honorée, en centièmes de la valeur livrée.
     */
    public static function presentEnCentiemes(Npc $courtisan): int
    {
        return QuetesDeChantier::PRESENT_EN_CENTIEMES + self::PRESENT_PAR_POINT_D_HUMEUR * self::humeurDe($courtisan);
    }

    /** Une requête honorée : il s'en souvient, un peu. */
    public function honorer(Npc $courtisan, int $quinzaine): void
    {
        $this->bouger($courtisan, 1);
        $courtisan->noter($quinzaine, 'sert');
    }

    /** Une requête refusée, ou un délai manqué. */
    public function decevoir(Npc $courtisan, int $quinzaine): void
    {
        $this->bouger($courtisan, -1);
        $courtisan->noter($quinzaine, 'decu');
    }

    /**
     * Le ton d'une requête, selon l'humeur : une préface, jamais une menace.
     */
    public static function prefaceDe(Npc $courtisan): string
    {
        $titre = $courtisan->getNom();

        return match (true) {
            self::humeurDe($courtisan) >= 3 => \sprintf('%s vous sait gré de vos services. ', $titre),
            self::humeurDe($courtisan) <= -3 => \sprintf('%s s\'impatiente. ', $titre),
            default => \sprintf('%s transmet : ', $titre),
        };
    }

    public static function libelleDeLHumeur(int $humeur): string
    {
        return match (true) {
            $humeur >= 3 => 'bienveillant',
            $humeur <= -3 => 'impatient',
            default => 'réservé',
        };
    }

    private function bouger(Npc $courtisan, int $delta): void
    {
        $humeur = max(-self::HUMEUR_MAX, min(self::HUMEUR_MAX, self::humeurDe($courtisan) + $delta));
        $courtisan->definirLeProfil(['humeur' => $humeur] + $courtisan->getProfil());
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
