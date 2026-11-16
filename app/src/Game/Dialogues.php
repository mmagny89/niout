<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que les gens se disent quand ils se croisent (`docs/plan-pnj-ville-vivante.md`, lot V4a).
 *
 * **Un dialogue dit un fait vrai, ou se déclare ambiance.** C'est la règle qui permet
 * de les écrire sans mentir au joueur : une réplique de situation (`fievre`, `chantier`,
 * `convoi`, `rival`, `sans_ouvrage`, `saison_*`, `fete`) n'est choisie **que si le fait
 * est vrai** dans la quinzaine ; une réplique d'ambiance (`ambiance`, `ambiance_soir`)
 * ne dit rien du jeu et se déclare telle. **Aucune réplique ne donne un chiffre, un
 * prix, un lieu de gisement, ni une réponse d'énigme** : ce qu'elle porte, le jeu le sait
 * déjà et le montre ailleurs.
 *
 * **Un catalogue, jamais du texte à la volée** : chaque situation a au moins quatre
 * variantes pour celui qui parle et quatre pour celui qui répond, pour que la
 * répétition ne lasse pas. Pas de nom propre attesté — celui d'un chef vient du jeu —,
 * et les dieux nommés sont ceux que le jeu emploie déjà (Rê, Sekhmet, Amon).
 *
 * Le texte se compose **à la lecture**, à partir de la situation, d'une variante et de
 * quelques variables : la trace ne garde aucune phrase.
 */
final class Dialogues
{
    public const string FAIT = 'fait';

    public const string AMBIANCE = 'ambiance';

    /**
     * Les situations : leur nature, ce que dit celui qui parle (`a`), ce que répond
     * l'autre (`b`).
     *
     * @return array<string, array{nature: string, a: list<string>, b: list<string>}>
     */
    public static function catalogue(): array
    {
        return [
            'fete' => [
                'nature' => self::FAIT,
                'a' => [
                    'Quelle belle journée pour {fete}.',
                    'On a veillé tard hier pour {fete}.',
                    'Tu viens à la procession de {fete} ?',
                    'Les gens sont sortis en nombre pour {fete}.',
                ],
                'b' => [
                    'Que les dieux nous gardent.',
                    'J\'ai mis ma plus belle toile.',
                    'Je ne la manquerais pour rien.',
                    'Il y aura du monde au Temple.',
                ],
            ],
            'fievre' => [
                'nature' => self::FAIT,
                'a' => [
                    'La fièvre court dans les maisons.',
                    'On dit que Sekhmet est en colère.',
                    'J\'ai peur pour les enfants.',
                    'Il faudrait brûler de l\'encens pour les malades.',
                ],
                'b' => [
                    'Que Sekhmet nous épargne.',
                    'Reste loin des malades.',
                    'Les anciens disent que cela passera.',
                    'Le Temple a besoin d\'offrandes.',
                ],
            ],
            'chantier' => [
                'nature' => self::FAIT,
                'a' => [
                    'Les maçons ne chôment pas.',
                    'Un chantier est en cours, on l\'entend d\'ici.',
                    'On entend les maillets jusqu\'ici.',
                    'Il y a du travail pour ceux qui savent bâtir.',
                ],
                'b' => [
                    'Pourvu que les vivres tiennent.',
                    'Ce sera un bel ouvrage.',
                    'Il faudra encore des bras.',
                    'Espérons qu\'il soit prêt à temps.',
                ],
            ],
            'convoi' => [
                'nature' => self::FAIT,
                'a' => [
                    'Un convoi a pris la route de {partenaire}.',
                    'Le chemin de {partenaire} est long.',
                    'Qu\'apportera-t-on de {partenaire} ?',
                    'On ne parle que de {partenaire} sur la place.',
                ],
                'b' => [
                    'Que les dieux gardent la route.',
                    'Pourvu qu\'il revienne chargé.',
                    'Il faut du courage pour ces routes.',
                    'On verra bien ce qu\'il rapporte.',
                ],
            ],
            'rival' => [
                'nature' => self::FAIT,
                'a' => [
                    'Tu as vu {rival} sur la place ?',
                    '{rival} cherche à prendre sa part de nos convois.',
                    'Méfie-toi de {rival}.',
                    '{rival} est encore là…',
                ],
                'b' => [
                    'Il ne manque pas de culot.',
                    'Laissons-le s\'user.',
                    'Les affaires sont les affaires.',
                    'On ne l\'a pas invité.',
                ],
            ],
            'sans_ouvrage' => [
                'nature' => self::FAIT,
                'a' => [
                    'Pas d\'ouvrage aujourd\'hui.',
                    'Je cherche du travail depuis le matin.',
                    'Les bras ne manquent pas, c\'est l\'ouvrage qui manque.',
                    'On finira bien par m\'employer.',
                ],
                'b' => [
                    'Patience, un poste finira par s\'ouvrir.',
                    'Reviens demain, on ne sait jamais.',
                    'Ce n\'est la faute de personne.',
                    'Les jours se suivent et ne se ressemblent pas.',
                ],
            ],
            'saison_akhet' => [
                'nature' => self::FAIT,
                'a' => [
                    'C\'est la saison de l\'inondation.',
                    'On attend la crue, comme chaque année.',
                    'Le fleuve commande, en Akhèt.',
                    'Les champs attendent le limon.',
                ],
                'b' => [
                    'Que Hâpi soit généreux.',
                    'Cette saison-là, on ne sème pas.',
                    'Les anciens regardent le fleuve tous les jours.',
                    'Il faut bien patienter.',
                ],
            ],
            'saison_peret' => [
                'nature' => self::FAIT,
                'a' => [
                    'C\'est le temps des semailles.',
                    'La terre émerge, il faut la travailler.',
                    'Le limon a séché : au travail.',
                    'Perèt commence, les champs réclament des bras.',
                ],
                'b' => [
                    'Que la terre soit bonne.',
                    'Il faut semer tant que c\'est humide.',
                    'Les grains sont prêts.',
                    'Plus vite on sème, mieux on récolte.',
                ],
            ],
            'saison_chemou' => [
                'nature' => self::FAIT,
                'a' => [
                    'C\'est la saison des moissons.',
                    'Le soleil écrase tout, en Chémou.',
                    'Il faut moissonner avant le retour du fleuve.',
                    'Chémou : tout est sec, tout est doré.',
                ],
                'b' => [
                    'Que Rê ne frappe pas trop fort.',
                    'On boira beaucoup d\'eau.',
                    'Les épis sont mûrs.',
                    'Le travail n\'attend pas.',
                ],
            ],
            // Un témoignage que l'émissaire vient de rapporter, rejoué en paroles (`Indice::parole()`).
            // **Le texte est celui de l'indice, rien de plus, sur le même ton pour tous** : le doc 10
            // interdit qu'un ton trahisse la nature d'un indice.
            'temoignage' => [
                'nature' => self::FAIT,
                'a' => [
                    'L\'émissaire revient. {temoin} dit : « {parole} »',
                    '{temoin} a parlé à notre émissaire : « {parole} »',
                    'Voici ce qu\'on a recueilli — {temoin} : « {parole} »',
                    'Notre émissaire rapporte, de la part de {temoin} : « {parole} »',
                ],
                'b' => [
                    'Qu\'on l\'inscrive au dossier.',
                    'Il faudra y réfléchir.',
                    'Voyons ce que cela vaut.',
                    'Les scribes en prendront note.',
                ],
            ],
            'ambiance' => [
                'nature' => self::AMBIANCE,
                'a' => [
                    'Le soleil est déjà haut.',
                    'Il fait bon à l\'ombre d\'un mur.',
                    'Bonne journée à toi.',
                    'Rien de neuf, alors ?',
                ],
                'b' => [
                    'Rien de neuf, que les dieux en soient remerciés.',
                    'Les jours se suivent.',
                    'Allons, au travail.',
                    'À la grâce de Rê.',
                ],
            ],
            'ambiance_soir' => [
                'nature' => self::AMBIANCE,
                'a' => [
                    'Le soleil descend, enfin.',
                    'On a bien travaillé aujourd\'hui.',
                    'À demain, que Rê te garde.',
                    'La journée a été longue.',
                ],
                'b' => [
                    'À demain.',
                    'Repose-toi bien.',
                    'Que la nuit te soit douce.',
                    'Rentrons avant la nuit.',
                ],
            ],
        ];
    }

    /**
     * La nature d'une situation : un fait vrai, ou une ambiance.
     */
    public static function nature(string $situation): string
    {
        return self::catalogue()[$situation]['nature'] ?? self::AMBIANCE;
    }

    /**
     * La réplique de celui qui parle (`a`) ou de celui qui répond (`b`), pour une variante
     * donnée — prise modulo le nombre de variantes — et ses variables.
     *
     * @param array<string, string> $variables `{fete}`, `{partenaire}`, `{rival}`, `{temoin}`, `{parole}`
     */
    public static function replique(string $situation, string $qui, int $variante, array $variables = []): string
    {
        $entree = self::catalogue()[$situation] ?? null;

        if (null === $entree) {
            return '';
        }

        $lignes = 'a' === $qui ? $entree['a'] : $entree['b'];

        $texte = $lignes[abs($variante) % \count($lignes)];

        foreach ($variables as $nom => $valeur) {
            $texte = str_replace('{'.$nom.'}', $valeur, $texte);
        }

        return $texte;
    }
}
