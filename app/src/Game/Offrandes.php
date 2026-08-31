<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\FaveurDivine;
use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Porter une offrande au Temple (doc 07).
 *
 * C'est le seul geste du jeu qui **n'a pas de contrepartie immédiate** : on
 * donne, la faveur monte, et ce qu'elle change se verra plus tard, ou pas du
 * tout si l'on s'arrête là. Tout le reste de l'économie se calcule — un
 * Grenier rapporte tant, un convoi rapporte tant.
 *
 * **On offre en deben ou en ressources**, au choix (décision de la joueuse).
 * La conversion passe par le **cours du Marché** (`PrixDuMarche`), jamais par
 * un second barème : deux tables de valeurs finiraient par diverger, et l'une
 * des deux deviendrait la bonne affaire à exploiter. Conséquence assumée —
 * une région qui produit cher honore ses dieux à moindre effort. L'Égypte
 * offrait ce qu'elle avait.
 *
 * C'est aussi le premier débouché du surplus que le plafond de stock refuse :
 * un Grenier plein ne se vide plus seulement au Marché.
 */
final readonly class Offrandes
{
    /**
     * Le barème du doc 07 : cinq points de faveur pour dix deben offerts, ou
     * leur équivalent en marchandise.
     *
     * **Provisoire, et il doit le rester** : porter une divinité de Neutre à
     * Dévoué coûte une soixantaine de deben, quand une quinzaine de salaires
     * en coûte près de quarante. Le chiffre vient du document ; c'est la
     * mesure en partie qui tranchera, comme au lot 4.6.
     */
    public const int POINTS_PAR_OFFRANDE = 5;
    public const int DEBEN_PAR_OFFRANDE = 10;

    /**
     * Ce qu'une offrande gagne à être portée **pendant la fête de son dieu**
     * (doc 07). Un supplément forfaitaire, non un multiplicateur : c'est le
     * moment qui compte, pas la générosité. Une poignée de blé offerte à Opet
     * vaut donc bien plus qu'un lingot offert la veille — ce qui est
     * exactement ce qu'une fête doit produire.
     */
    public const int POINTS_DE_FETE = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int les points de faveur réellement gagnés
     *
     * @throws OffrandeImpossible
     */
    public function offrir(GameSave $partie, Divinite $divinite, Ressource $ressource, int $quantite): int
    {
        $ville = $partie->getVille();

        if (!$ville->possede(TypeDeBatiment::Temple)) {
            throw new OffrandeImpossible('Il vous faut un Temple pour porter une offrande.');
        }

        if ($quantite < 1) {
            throw new OffrandeImpossible('Il faut offrir au moins une unité.');
        }

        $valeur = self::valeurDe($ressource, $quantite);

        if (null === $valeur) {
            throw new OffrandeImpossible(\sprintf('Le %s ne s\'offre pas : rien ne lui donne de valeur.', $ressource->libelle()));
        }

        $points = intdiv($valeur * self::POINTS_PAR_OFFRANDE, self::DEBEN_PAR_OFFRANDE);

        if ($points < 1) {
            throw new OffrandeImpossible('Une offrande si maigre ne se remarque pas : offrez davantage.');
        }

        // Le supplément de fête s'ajoute **après** le seuil : une offrande
        // dérisoire ne devient pas remarquable parce qu'un jour est saint.
        $points += self::supplementDeFete($partie->dateDeJeu(), $divinite);

        $faveur = $ville->faveurDe($divinite);
        $plafond = Temple::plafondDeFaveur($ville);

        if (($faveur?->getFaveur() ?? Divinite::FAVEUR_DE_DEPART) >= $plafond) {
            throw new OffrandeImpossible(\sprintf('%s vous accorde déjà tout ce qu\'un Temple de ce niveau peut porter. Agrandissez-le.', $divinite->libelle()));
        }

        if (!Temple::peutEncorePorter($ville, $divinite)) {
            throw new OffrandeImpossible(\sprintf('Votre Temple ne peut honorer que %d divinité(s) à la fois. Délaissez-en une, ou agrandissez-le.', Temple::divinitesPortables($ville)));
        }

        if (!$ville->debiterRessources([$ressource->value => $quantite])) {
            throw new OffrandeImpossible(\sprintf('Vous n\'avez pas %d %s à offrir.', $quantite, $ressource->libelle()));
        }

        $faveur = $ville->suivreLaFaveurDe($divinite);
        $avant = $faveur->getFaveur();
        $faveur->recevoirUneOffrande($points);
        $this->ramenerAuPlafond($faveur, $plafond);

        $this->entityManager->persist($faveur);
        $this->entityManager->flush();

        return $faveur->getFaveur() - $avant;
    }

    /**
     * Ce que vaut une offrande, en deben. Le deben vaut lui-même ; tout le
     * reste passe par le cours du Marché.
     */
    public static function valeurDe(Ressource $ressource, int $quantite): ?int
    {
        if ($ressource->estLaMonnaie()) {
            return $quantite;
        }

        $cours = PrixDuMarche::pour($ressource);

        return null === $cours ? null : $cours * $quantite;
    }

    /**
     * Ce qu'il faut donner pour gagner un point de faveur, dans cette
     * ressource-là. Sert à l'écran : le joueur doit voir ce que son geste
     * pèse **avant** de le faire, comme pour un ordre commercial.
     */
    public static function pointsPour(Ressource $ressource, int $quantite, ?DateDeJeu $date = null, ?Divinite $divinite = null): int
    {
        $valeur = self::valeurDe($ressource, $quantite);

        if (null === $valeur) {
            return 0;
        }

        $points = intdiv($valeur * self::POINTS_PAR_OFFRANDE, self::DEBEN_PAR_OFFRANDE);

        if ($points < 1) {
            return 0;
        }

        return $points + (null !== $date && null !== $divinite ? self::supplementDeFete($date, $divinite) : 0);
    }

    /**
     * Ce que la date ajoute, si elle tombe pendant la fête **de ce dieu-là**.
     * Une offrande à Ptah pendant Opet reste une offrande ordinaire : la fête
     * est un rendez-vous, pas une saison faste.
     */
    public static function supplementDeFete(DateDeJeu $date, Divinite $divinite): int
    {
        $fete = FeteCalendaire::pour($date);

        return null !== $fete && $fete->divinite() === $divinite ? self::POINTS_DE_FETE : 0;
    }

    /**
     * Ce que la ville peut porter au Temple : ses lignes de stock non vides
     * qui ont une valeur, deben compris — lui seul n'a pas de cours et vaut
     * pourtant.
     *
     * @return list<array{ressource: Ressource, quantite: int, valeurUnitaire: int}>
     */
    public function corbeillePour(GameSave $partie): array
    {
        $corbeille = [];

        foreach ($partie->getVille()->getStock() as $ligne) {
            $ressource = $ligne->getRessource();
            $valeur = self::valeurDe($ressource, 1);

            if (null === $valeur || $ligne->getQuantite() < 1) {
                continue;
            }

            $corbeille[] = [
                'ressource' => $ressource,
                'quantite' => $ligne->getQuantite(),
                'valeurUnitaire' => $valeur,
            ];
        }

        return $corbeille;
    }

    /**
     * Le plafond du Temple s'applique **après** le gain, jamais avant : le
     * joueur qui offre trop ne perd rien de plus que le débordement, et
     * l'écran le lui a dit.
     */
    private function ramenerAuPlafond(FaveurDivine $faveur, int $plafond): void
    {
        if ($faveur->getFaveur() > $plafond) {
            $faveur->ajuster($plafond - $faveur->getFaveur());
        }
    }
}
