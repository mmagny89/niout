<?php

declare(strict_types=1);

namespace App\Twig;

use App\Game\EmplacementsDeLaVille;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * Les illustrations du jeu, trouvées d'après la valeur de leur énumération :
 *
 * - `image_de_ressource(r)` → `images/ressources/<valeur>.webp` — une `Ressource`,
 *   une `Recette` (leurs valeurs coïncident : `poterie`, `pain`…) ou une chaîne ;
 * - `image_de_divinite(d)` → `images/dieux/<valeur>.webp` ;
 * - `image_de_batiment(type, niveau)` → `images/ville/batiments/<type>_<palier>.webp` — le sprite du
 *   palier d'un bâtiment (`min(niveau, 4)`, les planches n'en livrent que quatre) ;
 * - `image_d_interface(nom)` → `images/interface/<nom>.webp` — les pictogrammes de
 *   l'interface (deben, habitants, danger…), nommés d'après leur **usage**.
 *
 * **Une image manquante n'est pas une erreur** : la fonction rend `null` et le
 * gabarit s'en passe — le deben, le poisson, les dattes, les outils et les armes
 * n'ont pas encore leur planche, et l'écran doit rester juste sans elles. Le
 * fichier est testé sur disque, comme `BatimentsDeLaCite`.
 */
final readonly class IllustrationsExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $racine,
    ) {
    }

    #[AsTwigFunction('image_de_ressource')]
    public function imageDeRessource(mixed $ressource): ?string
    {
        return $this->chemin('ressources', $ressource);
    }

    #[AsTwigFunction('image_de_divinite')]
    public function imageDeDivinite(mixed $divinite): ?string
    {
        return $this->chemin('dieux', $divinite);
    }

    #[AsTwigFunction('image_de_batiment')]
    public function imageDeBatiment(mixed $type, int $niveau = 1): ?string
    {
        $cle = $type instanceof \BackedEnum ? (string) $type->value : (\is_string($type) ? $type : null);

        if (null === $cle || 1 !== preg_match('/^[a-z_]{1,40}$/', $cle)) {
            return null;
        }

        $fichier = \sprintf('%s_%d.webp', $cle, EmplacementsDeLaVille::palierDeSprite($niveau));

        return is_file(\sprintf('%s/assets/images/ville/batiments/%s', $this->racine, $fichier))
            ? 'images/ville/batiments/'.$fichier
            : null;
    }

    #[AsTwigFunction('image_d_interface')]
    public function imageDInterface(mixed $nom): ?string
    {
        return $this->chemin('interface', $nom);
    }

    private function chemin(string $dossier, mixed $valeur): ?string
    {
        $cle = $valeur instanceof \BackedEnum ? (string) $valeur->value : (\is_string($valeur) ? $valeur : null);

        // Une valeur vient d'une énumération, pas d'une requête ; on la contraint
        // quand même : elle finit dans un chemin de fichier.
        if (null === $cle || 1 !== preg_match('/^[a-z_]{1,40}$/', $cle)) {
            return null;
        }

        return is_file(\sprintf('%s/assets/images/%s/%s.webp', $this->racine, $dossier, $cle))
            ? \sprintf('images/%s/%s.webp', $dossier, $cle)
            : null;
    }
}
