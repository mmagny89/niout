<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Random\Randomizer;

/**
 * Bénédictions et malédictions : ce qu'un dieu fait sans qu'on le lui demande
 * (doc 07).
 *
 * Le document les pose en miroir — le palier Dévoué ouvre « une chance
 * d'événement de bénédiction ponctuel », le palier Hostile prolongé « un
 * événement de malédiction », « symétrique à la bénédiction ». Ce sont les
 * seuls effets divins qui **surviennent** au lieu de s'appliquer en continu :
 * tout le reste de la faveur (lot 6.3) est un réglage permanent qu'on oublie.
 *
 * Trois règles à ne pas défaire :
 *
 * **Une malédiction retarde et coûte, elle n'efface pas** (décision de la
 * joueuse) : jamais de perte définitive, jamais de bâtiment détruit, et
 * surtout **jamais d'échec de partie** — la famine reste la seule cause de
 * défaite du jeu. Une malédiction peut affamer la ville ; c'est alors la
 * famine qui conclut, à ses douze quinzaines, et le joueur a le temps de
 * réagir.
 *
 * **Aucune ne multiplie une production.** Elles donnent, elles retirent, elles
 * ajournent — jamais un facteur de plus sur une chaîne qui en porte déjà un.
 * C'est la discipline du lot 6.3, et elle vaut aussi pour ce qui surgit.
 *
 * **Le hasard passe par un `Randomizer` injecté**, comme la crue et les
 * candidats : semé en test, il rend l'événement reproductible sans que le code
 * de production ait à connaître la différence.
 */
final readonly class Providence
{
    /**
     * Chance, par quinzaine et par dieu concerné, qu'il se manifeste.
     *
     * **Valeur inventée** : le doc parle de « chance », jamais de taux. Huit
     * pour cent donnent un peu plus d'un événement par an et par dieu dévoué —
     * assez rare pour rester un événement, assez fréquent pour valoir la peine
     * d'avoir mené un dieu jusque-là.
     */
    public const int CHANCE_PAR_QUINZAINE = 8;

    /**
     * Ce qu'une bénédiction verse, et ce qu'une malédiction gâte — en unités
     * de ressource. **Valeurs inventées**, du même ordre qu'une quinzaine de
     * récolte : de quoi se remarquer sans refaire l'économie d'une partie.
     */
    public const int PRESENT_EN_VIVRES = 30;
    public const int PRESENT_EN_MATERIAUX = 20;
    public const int PRESENT_EN_DEBEN = 25;

    /**
     * La part des vivres qu'une colère divine gâte, en centièmes. **Jamais
     * tout** : un grenier vidé d'un coup serait une perte définitive déguisée
     * en événement.
     */
    public const int PART_GATEE = 20;

    /**
     * Ce qu'une malédiction ajourne, en dixièmes de cycle — soit une quinzaine
     * de chantier, ou de trajet pour un convoi.
     */
    public const int AJOURNEMENT = 10;

    /**
     * Ce qu'une quinzaine de famine coûte à chaque dieu déjà engagé. C'est la
     * seule perte de faveur qui **franchit le plancher du neutre** : la
     * négligence, elle, s'y arrête.
     */
    public const int PERTE_PAR_QUINZAINE_DE_FAMINE = 1;

    public function __construct(
        private GeographieDeLaPartie $geographies,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $evenements = [...$this->eloignerLesDieuxDUneVilleAffamee($partie)];

        $geographie = $this->geographies->pour($partie);

        foreach ($partie->getVille()->getFaveurs() as $faveur) {
            $palier = $faveur->getPalier();

            if (!$palier->estAuDessusDuNeutre() && !$palier->nuit()) {
                continue;
            }

            // Un dieu sans prise sur cette région ne se manifeste pas : Hâpi
            // n'a ni gerbe à faire lever ni crue à retenir là où aucun fleuve
            // ne monte. Une bénédiction venue de lui au Sinaï serait la même
            // incohérence que l'offrande qu'on lui refuse.
            if ($faveur->getDivinite()->estSansDomaineIci($geographie)) {
                continue;
            }

            // Un dieu favorable n'est pas dévoué : seule la pleine dévotion
            // fait se manifester quelqu'un.
            if (PalierDeFaveur::Favorable === $palier) {
                continue;
            }

            if ($this->hasard->getInt(1, 100) > self::CHANCE_PAR_QUINZAINE) {
                continue;
            }

            $evenements[] = PalierDeFaveur::Devoue === $palier
                ? $this->benir($partie, $faveur->getDivinite())
                : $this->maudire($partie, $faveur->getDivinite());
        }

        return $evenements;
    }

    /**
     * **La faim est la seule chose qui fâche vraiment un dieu**, et c'est la
     * seule source d'hostilité du jeu à ce jour.
     *
     * Sans elle, la branche « malédiction » serait du code mort : la
     * négligence s'arrête au neutre (lot 6.2), et les quêtes ratées comme les
     * choix moraux du doc 07 relèvent des Phases 7 et 8. Le piège
     * d'`ajusterRenommee()`, resté inerte des mois durant faute d'une source,
     * ne se repaie pas.
     *
     * Le mécanisme est littéral : une ville qui ne se nourrit plus ne nourrit
     * plus ses dieux — les offrandes s'arrêtent avec le reste. Il ne frappe
     * que les divinités qu'on avait engagées : ne jamais mettre les pieds au
     * Temple ne coûte toujours rien.
     *
     * @return list<string>
     */
    private function eloignerLesDieuxDUneVilleAffamee(GameSave $partie): array
    {
        if ($partie->getQuinzainesDeFamine() < Subsistance::SEUIL_DE_FAMINE) {
            return [];
        }

        $franchis = [];

        foreach ($partie->getVille()->getFaveurs() as $faveur) {
            $avant = $faveur->getPalier();
            $faveur->ajuster(-self::PERTE_PAR_QUINZAINE_DE_FAMINE);

            if ($faveur->getPalier() !== $avant && $faveur->getPalier()->nuit()) {
                $franchis[] = \sprintf(
                    'La ville n\'a plus rien à offrir : %s vous devient hostile.',
                    $faveur->getDivinite()->libelle(),
                );
            }
        }

        return $franchis;
    }

    /**
     * Un présent, tiré du domaine du dieu. Jamais un effet permanent : une
     * bénédiction qui installerait un bonus se confondrait avec le palier
     * lui-même.
     */
    private function benir(GameSave $partie, Divinite $divinite): string
    {
        return match ($divinite) {
            Divinite::Hapi, Divinite::Osiris => $this->offrir(
                $partie,
                [Ressource::Ble->value => self::PRESENT_EN_VIVRES],
                \sprintf('%s vous fait la grâce d\'une gerbe de plus : %d blés entrent au Grenier.', $divinite->libelle(), self::PRESENT_EN_VIVRES),
            ),
            Divinite::Ptah => $this->offrir(
                $partie,
                [Ressource::Argile->value => self::PRESENT_EN_MATERIAUX, Ressource::BoisLocal->value => self::PRESENT_EN_MATERIAUX],
                \sprintf('Les artisans de Ptah livrent argile et bois sans rien demander : %d de chaque.', self::PRESENT_EN_MATERIAUX),
            ),
            Divinite::Sobek => $this->offrir(
                $partie,
                [Ressource::Deben->value => self::PRESENT_EN_DEBEN],
                \sprintf('Un chargement qu\'on croyait perdu remonte le fleuve : %d deben.', self::PRESENT_EN_DEBEN),
            ),
            // Amon-Rê ne donne pas de marchandise : il fait parler de vous.
            Divinite::AmonRe => $this->faireParler($partie),
            default => \sprintf('%s vous est favorable.', $divinite->libelle()),
        };
    }

    /**
     * Un revers, du même domaine. Il retarde ou il coûte ; il n'efface pas.
     */
    private function maudire(GameSave $partie, Divinite $divinite): string
    {
        return match ($divinite) {
            Divinite::Hapi, Divinite::Osiris => $this->gaterLesVivres($partie, $divinite),
            Divinite::Ptah => $this->ajournerLesChantiers($partie),
            Divinite::Sobek => $this->ajournerLesConvois($partie),
            Divinite::AmonRe => $this->faireMedire($partie),
            default => \sprintf('%s se détourne de vous.', $divinite->libelle()),
        };
    }

    /**
     * @param array<string, int> $present
     */
    private function offrir(GameSave $partie, array $present, string $message): string
    {
        // Le plafond de réserve s'applique comme à toute entrée : un présent
        // divin ne fait pas déborder un Grenier plein, il se perd.
        $partie->getVille()->crediterRessources($present);

        return $message;
    }

    private function faireParler(GameSave $partie): string
    {
        $partie->getFamille()->ajusterRenommee(1);

        return 'On parle de votre famille jusqu\'à Karnak : Amon-Rê vous vaut un point de renommée.';
    }

    private function faireMedire(GameSave $partie): string
    {
        $partie->getFamille()->ajusterRenommee(-1);

        return 'On dit du mal de votre famille jusqu\'à Karnak : Amon-Rê vous coûte un point de renommée.';
    }

    private function gaterLesVivres(GameSave $partie, Divinite $divinite): string
    {
        $ville = $partie->getVille();
        $gate = 0;

        foreach (Ressource::cases() as $ressource) {
            if (!$ressource->estNourriture()) {
                continue;
            }

            // Une part, jamais tout : un grenier vidé d'un coup serait une
            // perte définitive déguisée en événement.
            $part = intdiv($ville->quantite($ressource) * self::PART_GATEE, 100);

            if ($part > 0 && $ville->debiterRessources([$ressource->value => $part])) {
                $gate += $part;
            }
        }

        return 0 === $gate
            ? \sprintf('%s se détourne, mais vos réserves sont déjà vides.', $divinite->libelle())
            : \sprintf('%s se détourne : %d vivres se gâtent au Grenier.', $divinite->libelle(), $gate);
    }

    private function ajournerLesChantiers(GameSave $partie): string
    {
        $ajournes = 0;

        foreach ($partie->getVille()->getChantiers() as $chantier) {
            $chantier->retarder(self::AJOURNEMENT);
            ++$ajournes;
        }

        return 0 === $ajournes
            ? 'Ptah se détourne, mais aucun chantier n\'est ouvert.'
            : 'Ptah se détourne : les briques sèchent mal, les travaux prennent une quinzaine de retard.';
    }

    private function ajournerLesConvois(GameSave $partie): string
    {
        $ajournes = 0;

        foreach ($partie->getVille()->getRoutesCommerciales() as $route) {
            foreach ($route->getConvois() as $convoi) {
                if (!$convoi->estRentre()) {
                    $convoi->retarder(1);
                    ++$ajournes;
                }
            }
        }

        return 0 === $ajournes
            ? 'Sobek se détourne, mais rien ne navigue.'
            : 'Sobek se détourne : les vents contrarient vos convois d\'une quinzaine.';
    }
}
