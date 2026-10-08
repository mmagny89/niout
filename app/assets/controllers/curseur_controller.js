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
    static targets = ['champ', 'piste', 'valeur', 'surplus', 'verdict', 'nombre'];
    static values = {
        seuil: Number,
        mode: { type: String, default: 'seuil' },
        reserve: Number,
        // Le mauvais côté est le bas (un salaire trop bas mécontente) et non le haut (un prix trop haut).
        mauvaisEnBas: Boolean,
        // Pour un verdict en trois temps (en dessous de l'usage, l'usage, au-delà) : les deux repères.
        juste: Number,
        genereux: Number,
    };

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
        const [avant, apres] = this.mauvaisEnBasValue
            ? ['var(--color-terre-500)', 'var(--color-lapis-400)']
            : ['var(--color-lapis-400)', 'var(--color-terre-500)'];

        this.pisteTarget.style.background = `linear-gradient(to right, ${avant} ${pourcent}%, ${apres} ${pourcent}%)`;

        if (this.hasNombreTarget) {
            this.nombreTarget.textContent = String(valeur);
        }
        if (this.hasValeurTarget) {
            const mauvais = this.mauvaisEnBasValue ? valeur < this.seuilValue : valeur > this.seuilValue;
            this.valeurTarget.classList.toggle('text-terre-600', mauvais);
        }
        if (this.hasVerdictTarget) {
            const cle = valeur < this.justeValue ? 'bas' : (valeur >= this.genereuxValue ? 'haut' : 'milieu');
            this.verdictTarget.textContent = this.verdictTarget.dataset[cle] ?? '';
            this.verdictTarget.classList.toggle('text-terre-600', cle === 'bas');
            this.verdictTarget.classList.toggle('text-lapis-600', cle !== 'bas');
        }
        if (this.hasSurplusTarget) {
            const part = Math.max(0, this.reserveValue - Math.max(0, valeur));
            this.surplusTarget.textContent = part > 0 ? `${part} partiront au Marché` : 'rien ne partira';
            this.surplusTarget.classList.toggle('text-terre-600', part > 0);
        }
    }
}
