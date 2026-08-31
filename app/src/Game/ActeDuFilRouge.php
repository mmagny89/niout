<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les trois actes d'une mission (doc 10).
 *
 * La structure est **répétable** : commande du pharaon et énigme d'ouverture,
 * obstacle résolu par une enquête, accomplissement. Elle n'est écrite ici que
 * pour la **mission 1** ; la Phase 8 la généralisera aux dix, une fois qu'on
 * aura joué celle-là.
 */
enum ActeDuFilRouge: string
{
    case Commande = 'commande';
    case Obstacle = 'obstacle';
    case Accomplissement = 'accomplissement';
    case Accompli = 'accompli';

    public function libelle(): string
    {
        return match ($this) {
            self::Commande => 'Acte I — la commande du pharaon',
            self::Obstacle => 'Acte II — l\'obstacle',
            self::Accomplissement => 'Acte III — l\'accomplissement',
            self::Accompli => 'La volonté du roi est accomplie',
        };
    }

    /**
     * Ce que le joueur a à faire, dit sans détour. Un fil rouge dont on ne
     * sait pas ce qu'il attend n'est pas un fil : c'est une décoration.
     */
    public function consigne(): string
    {
        return match ($this) {
            self::Commande => 'Ahmôsis a fait porter une tablette scellée. Faites-la lire à vos scribes.',
            self::Obstacle => 'Une terre fertile, à deux pas, que personne ne cultive. Trouvez pourquoi : fouillez les cases où quelque chose se trame, envoyez un émissaire écouter ce qui s\'y dit, puis tranchez.',
            self::Accomplissement => 'La route est rouverte. Vos scribes ont gravé la stèle qui le dira : relisez-la avant qu\'on la dresse.',
            self::Accompli => 'La stèle est dressée. Le commerce du Delta reprend, et c\'est ce qu\'Ahmôsis avait demandé.',
        };
    }
}
