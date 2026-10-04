<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * Les illustrations du jeu, trouvées d'après la valeur de leur énumération :
 *
 * - `image_de_ressource(r)` → `images/ressources/<valeur>.webp` — une `Ressource`,
 *   une `Recette` (leurs valeurs coïncident : `poterie`, `pain`…) ou une chaîne ;
 * - `image_de_divinite(d)` → `images/dieux/<valeur>.webp`.
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
