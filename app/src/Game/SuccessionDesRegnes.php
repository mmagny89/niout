<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La suite des règnes que traverse une partie Aventure (doc 14).
 *
 * C'est la différence structurelle avec la campagne : au lieu d'un pharaon
 * commanditaire unique, la partie traverse une succession **réelle**, dans son
 * ordre chronologique, et rencontre bien plus de souverains qu'une campagne de
 * dix missions — c'est l'argument pédagogique du document.
 *
 * **La liste est une donnée, jamais une constante** (arbitrage 11.0). La
 * première livraison porte la XVIIIᵉ dynastie ; les XIXᵉ et XXᵉ s'y ajouteront
 * sans toucher à une ligne de code, jusqu'à la fin du Nouvel Empire. Rien ici
 * ne suppose qu'elle s'arrête à un pharaon nommé.
 *
 * **Le règne en cours se déduit du cycle**, il ne se persiste pas : les durées
 * sont du contenu, donc la somme est connue d'avance. Une colonne de plus
 * n'aurait rien dit que cette liste ne sache déjà.
 *
 * **Les durées viennent du doc 14**, qui les range par catégorie plutôt que de
 * convertir les années une à une (`LongueurDeRegne`). Les faits d'avènement
 * sont attestés — même exigence que pour les cartouches et les stèles : rien
 * d'inventé, et rien d'affiché plutôt qu'une approximation.
 */
final readonly class SuccessionDesRegnes
{
    /**
     * La XVIIIᵉ dynastie, d'Ahmôsis Iᵉʳ à Horemheb.
     *
     * **Smenkhkarê n'y figure pas**, et c'est délibéré : son règne est le seul
     * de la dynastie dont l'existence propre, la durée et jusqu'à l'identité
     * sont débattus — on l'a tour à tour confondu avec Neferneferouaton et
     * placé avant ou après elle. La règle du projet interdit d'afficher ce qui
     * ne s'établit pas ; il rejoindra la liste si la question se tranche.
     *
     * @return list<Regne>
     */
    public function tous(): array
    {
        return [
            new Regne(
                pharaon: 'Ahmôsis Ier',
                dynastie: 18,
                anneesReelles: 25,
                dureeEnCycles: 20,
                avenement: 'Ahmôsis chasse les Hyksôs d\'Avaris et rend le Delta à l\'Égypte. Une dynastie commence.',
            ),
            new Regne(
                pharaon: 'Amenhotep Ier',
                dynastie: 18,
                anneesReelles: 21,
                dureeEnCycles: 18,
                avenement: 'Amenhotep succède à son père et affermit ce qu\'il a repris. Les artisans de la nécropole thébaine le tiendront longtemps pour leur patron.',
            ),
            new Regne(
                pharaon: 'Thoutmôsis Ier',
                dynastie: 18,
                anneesReelles: 13,
                dureeEnCycles: 12,
                avenement: 'Thoutmôsis porte la frontière jusqu\'à l\'Euphrate, et se fait creuser une tombe dans une vallée déserte de l\'autre rive.',
            ),
            new Regne(
                pharaon: 'Thoutmôsis II',
                dynastie: 18,
                anneesReelles: 13,
                dureeEnCycles: 12,
                avenement: 'Un règne bref, occupé au sud : la Nubie s\'agite, et les garnisons s\'y renforcent.',
            ),
            new Regne(
                pharaon: 'Hatchepsout',
                dynastie: 18,
                anneesReelles: 22,
                dureeEnCycles: 20,
                avenement: 'Hatchepsout règne, et son expédition revient de Pount chargée d\'encens et d\'arbres vivants. Son temple s\'adosse à la falaise de Deir el-Bahari.',
            ),
            new Regne(
                pharaon: 'Thoutmôsis III',
                dynastie: 18,
                anneesReelles: 54,
                dureeEnCycles: 30,
                avenement: 'Thoutmôsis règne seul, et mènera dix-sept campagnes. La première l\'a conduit devant Megiddo.',
            ),
            new Regne(
                pharaon: 'Amenhotep II',
                dynastie: 18,
                anneesReelles: 26,
                dureeEnCycles: 21,
                avenement: 'Amenhotep tient de son père le goût de la Syrie et celui des exploits : on vante son arc, ses chevaux et sa rame.',
            ),
            new Regne(
                pharaon: 'Thoutmôsis IV',
                dynastie: 18,
                anneesReelles: 10,
                dureeEnCycles: 11,
                avenement: 'Thoutmôsis fait dresser entre les pattes du grand sphinx de Giza la stèle du songe qui, dit-elle, lui promit la couronne.',
            ),
            new Regne(
                pharaon: 'Amenhotep III',
                dynastie: 18,
                anneesReelles: 38,
                dureeEnCycles: 32,
                avenement: 'L\'Égypte est riche et en paix. Amenhotep bâtit son palais de Malkata et deux colosses veillent devant son temple de millions d\'années.',
            ),
            new Regne(
                pharaon: 'Akhenaton',
                dynastie: 18,
                anneesReelles: 17,
                dureeEnCycles: 17,
                avenement: 'Le roi ne reconnaît plus qu\'Aton, le disque, et fonde en terre vierge une capitale neuve : Akhetaton.',
            ),
            new Regne(
                pharaon: 'Toutânkhamon',
                dynastie: 18,
                anneesReelles: 10,
                dureeEnCycles: 11,
                avenement: 'L\'enfant-roi quitte Akhetaton et rend leurs temples aux anciens dieux. Son nom même passe d\'Aton à Amon.',
            ),
            new Regne(
                pharaon: 'Aÿ',
                dynastie: 18,
                anneesReelles: 4,
                dureeEnCycles: 10,
                avenement: 'Le vieux conseiller ceint la couronne à son tour, pour peu d\'années.',
            ),
            new Regne(
                pharaon: 'Horemheb',
                dynastie: 18,
                anneesReelles: 14,
                dureeEnCycles: 13,
                avenement: 'Un général monte sur le trône et remet l\'administration d\'aplomb : son édit menace nommément ceux qui pressurent les gens.',
            ),
        ];
    }

    /**
     * Le règne en cours à ce cycle, ou **null quand la succession est
     * épuisée** — la partie s'achève alors (lot 11.4).
     */
    public function auCycle(int $cycle): ?Regne
    {
        $rang = $this->rangAuCycle($cycle);

        return null === $rang ? null : $this->tous()[$rang];
    }

    /**
     * Le rang du règne en cours, à partir de zéro. Null passé le dernier.
     */
    public function rangAuCycle(int $cycle): ?int
    {
        $debut = 1;

        foreach ($this->tous() as $rang => $regne) {
            if ($cycle < $debut + $regne->dureeEnCycles) {
                return $rang;
            }

            $debut += $regne->dureeEnCycles;
        }

        return null;
    }

    /**
     * Vrai quand ce cycle voit un pharaon succéder à un autre. **Faux au
     * premier cycle** : le règne d'ouverture n'est une succession pour
     * personne, la ville existait avant lui.
     */
    public function estUneAnneeDAvenement(int $cycle): bool
    {
        if ($cycle <= 1) {
            return false;
        }

        return $this->rangAuCycle($cycle) !== $this->rangAuCycle($cycle - 1);
    }

    /**
     * Le cycle où la succession s'épuise — la fin de la partie.
     */
    public function dernierCycle(): int
    {
        $total = 0;

        foreach ($this->tous() as $regne) {
            $total += $regne->dureeEnCycles;
        }

        return $total;
    }

    public function estEpuisee(int $cycle): bool
    {
        return null === $this->rangAuCycle($cycle);
    }
}
