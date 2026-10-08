import { Controller } from '@hotwired/stimulus';

/*
 * Un nombre réglé à la souris *et* au clavier : un curseur et un champ numérique liés.
 *
 * Le champ reste la source (c'est lui qu'on soumet, et il marche sans JavaScript) ; le
 * curseur le double. Le serveur borne et valide.
 *
 * Deux modes, selon ce que la piste doit dire :
 * - `seuil` (par défaut) : la piste est teintée une fois pour toutes, lapis puis terre
 *   cuite à partir de `data-curseur-seuil-value` — le prix que la ville tolère ;
 * - `part` : la piste se partage **à la position du curseur**, lapis pour ce qu'on garde,
 *   terre cuite pour ce qui part. `data-curseur-reserve-value` est ce qu'on possède, et
 *   la cible `surplus` dit en toutes lettres combien partira.
 */
export default class extends Controller {
    static targets = ['champ', 'piste', 'valeur', 'surplus'];
    static values = { seuil: Number, mode: { type: String, default: 'seuil' }, reserve: Number };

    connect() {
        this.pisteTarget.min = this.champTarget.min || 0;
        this.pisteTarget.max = this.modeValue === 'part' ? this.reserveValue : this.champTarget.max;
        this.pisteTarget.step = this.champTarget.step || 1;
        this.pisteTarget.value = this.champTarget.value;
        this.afficher();
    }

    deplacer() {
        this.champTarget.value = this.pisteTarget.value;
        this.afficher();
    }

    saisir() {
        this.pisteTarget.value = this.champTarget.value;
        this.afficher();
    }

    afficher() {
        const min = Number(this.pisteTarget.min);
        const max = Number(this.pisteTarget.max);
        const valeur = Number(this.champTarget.value);
        const limite = this.modeValue === 'part' ? valeur : this.seuilValue;
        const part = max > min ? ((limite - min) / (max - min)) * 100 : 100;
        const pourcent = Math.min(100, Math.max(0, part));

        this.pisteTarget.style.background =
            `linear-gradient(to right, var(--color-lapis-400) ${pourcent}%, var(--color-terre-500) ${pourcent}%)`;

        if (this.hasValeurTarget) {
            this.valeurTarget.classList.toggle('text-terre-600', valeur > this.seuilValue);
        }
        if (this.hasSurplusTarget) {
            const part = Math.max(0, this.reserveValue - Math.max(0, valeur));
            this.surplusTarget.textContent = part > 0 ? `${part} partiront au Marché` : 'rien ne partira';
            this.surplusTarget.classList.toggle('text-terre-600', part > 0);
        }
    }
}
