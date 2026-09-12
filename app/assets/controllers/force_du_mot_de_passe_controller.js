import { Controller } from '@hotwired/stimulus';

/*
 * Ce que vaut le mot de passe qu'on est en train de taper.
 *
 * Les règles existaient déjà côté serveur — douze caractères, une force
 * minimale, et l'absence du mot de passe dans les fuites connues — mais rien
 * ne les disait avant l'échec. On les affiche, et on dit où l'on en est.
 *
 * **L'estimation est celle de Symfony, reproduite trait pour trait**, et non
 * une approximation maison : un indicateur qui annonce « fort » là où le
 * serveur refusera est pire que pas d'indicateur du tout. La source est
 * `PasswordStrengthValidator::estimateStrength()`. Le calcul porte sur les
 * **octets** et non sur les caractères, parce que `strlen` et `count_chars`
 * de PHP comptent des octets : un « é » y pèse deux, et l'ignorer ferait
 * diverger les deux mesures dès le premier accent.
 *
 * `SeuilsDeForceTest` garde l'alignement : il éprouve l'estimation de Symfony
 * sur des mots de passe témoins et échoue si elle change, ce qui signale que
 * cette reproduction est à reprendre.
 *
 * Ce qui n'est **pas** vérifié ici : l'appartenance à une fuite connue. Elle
 * demande d'interroger un service tiers, et cela ne se fait qu'au moment de
 * l'envoi, côté serveur. L'écran le dit plutôt que de le taire.
 */
export default class extends Controller {
    static targets = ['champ', 'jauge', 'verdict', 'critereLongueur', 'critereForce'];
    static values = {
        // Douze : la contrainte Length des deux formulaires.
        longueurMinimale: { type: Number, default: 12 },
        // 2 — « moyen » — est le minScore par défaut de la contrainte Symfony.
        forceMinimale: { type: Number, default: 2 },
    };

    static NIVEAUX = [
        { libelle: 'très faible', classe: 'bg-terre-500', part: '20%' },
        { libelle: 'faible', classe: 'bg-terre-500', part: '40%' },
        { libelle: 'moyen', classe: 'bg-ocre-500', part: '60%' },
        { libelle: 'fort', classe: 'bg-lapis-500', part: '80%' },
        { libelle: 'très fort', classe: 'bg-lapis-500', part: '100%' },
    ];

    connect() {
        this.evaluer();
    }

    evaluer() {
        const mdp = this.hasChampTarget ? this.champTarget.value : '';
        const octets = new TextEncoder().encode(mdp);
        const force = this.constructor.estimerLaForce(octets);

        this.afficherLaJauge(mdp.length > 0, force);
        this.marquer(this.critereLongueurTarget, octets.length >= this.longueurMinimaleValue);
        this.marquer(this.critereForceTarget, force >= this.forceMinimaleValue);
    }

    afficherLaJauge(saisi, force) {
        const niveau = this.constructor.NIVEAUX[force];

        this.jaugeTarget.className = `h-full rounded-sm transition-all ${saisi ? niveau.classe : ''}`;
        this.jaugeTarget.style.width = saisi ? niveau.part : '0%';
        this.verdictTarget.textContent = saisi ? niveau.libelle : '';
    }

    /**
     * Coche ou décoche un critère.
     *
     * Le mot « satisfait » est réservé aux lecteurs d'écran : la coche seule
     * est une information portée par la couleur et la forme, que rien
     * n'énoncerait autrement.
     */
    marquer(critere, satisfait) {
        critere.dataset.satisfait = satisfait ? 'oui' : 'non';
        critere.classList.toggle('text-lapis-600', satisfait);
        critere.classList.toggle('text-encre-500', !satisfait);
        critere.querySelector('[data-coche]').textContent = satisfait ? '✓' : '·';
        critere.querySelector('[data-etat]').textContent = satisfait ? ' — satisfait' : ' — pas encore';
    }

    /**
     * Reproduction exacte de PasswordStrengthValidator::estimateStrength().
     *
     * @param {Uint8Array} octets
     * @returns {number} de 0 (très faible) à 4 (très fort)
     */
    static estimerLaForce(octets) {
        const longueur = octets.length;

        if (0 === longueur) {
            return 0;
        }

        const comptes = new Map();

        for (const octet of octets) {
            comptes.set(octet, (comptes.get(octet) ?? 0) + 1);
        }

        const distincts = comptes.size;
        let controle = 0;
        let chiffre = 0;
        let majuscule = 0;
        let minuscule = 0;
        let symbole = 0;
        let autre = 0;

        for (const octet of comptes.keys()) {
            // L'ordre des cas suit celui du match() de PHP : le premier qui
            // s'applique gagne, et intervertir « autre » et « symbole »
            // changerait la taille du répertoire pour tout accent.
            if (octet < 32 || 127 === octet) {
                controle = 33;
            } else if (octet >= 48 && octet <= 57) {
                chiffre = 10;
            } else if (octet >= 65 && octet <= 90) {
                majuscule = 26;
            } else if (octet >= 97 && octet <= 122) {
                minuscule = 26;
            } else if (octet >= 128) {
                autre = 128;
            } else {
                symbole = 33;
            }
        }

        const repertoire = minuscule + majuscule + chiffre + symbole + controle + autre;
        const entropie = distincts * Math.log2(repertoire)
            + (longueur - distincts) * Math.log2(distincts);

        if (entropie >= 120) {
            return 4;
        }

        if (entropie >= 100) {
            return 3;
        }

        if (entropie >= 80) {
            return 2;
        }

        if (entropie >= 60) {
            return 1;
        }

        return 0;
    }
}
