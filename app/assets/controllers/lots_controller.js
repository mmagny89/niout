import { Controller } from '@hotwired/stimulus';

/*
 * Un ordre de fabrication : ce que « N lots » coûte et rapporte, avant d'engager.
 *
 * Le gabarit rend les quantités d'**un** lot (`data-base`) et le nombre de pièces d'un
 * lot (`data-lots-pieces-value`) ; ce contrôleur multiplie par le nombre de lots saisi.
 * C'est la même arithmétique que `Fabrication::matieresPour()`, qui reste seule juge —
 * elle borne les lots et vérifie les réserves. Sans JavaScript, le formulaire marche :
 * l'aperçu est un confort, et le texte rendu par le serveur dit déjà le coût d'un lot.
 */
export default class extends Controller {
    static targets = ['lots', 'matiere', 'pieces'];
    static values = { pieces: Number };

    connect() {
        this.calculer();
    }

    plus() {
        this.regler(this.lotsActuels() + 1);
    }

    moins() {
        this.regler(this.lotsActuels() - 1);
    }

    calculer() {
        const lots = this.lotsActuels();

        for (const element of this.matiereTargets) {
            const base = Number.parseInt(element.dataset.base ?? '0', 10);
            element.querySelector('[data-quantite]').textContent = String(base * lots);
        }
        if (this.hasPiecesTarget) {
            this.piecesTarget.textContent = `${lots * this.piecesValue} pièces à l'achèvement`;
        }
    }

    lotsActuels() {
        return Math.max(1, Number.parseInt(this.lotsTarget.value, 10) || 1);
    }

    regler(valeur) {
        const min = Number(this.lotsTarget.min) || 1;
        const max = Number(this.lotsTarget.max) || valeur;
        this.lotsTarget.value = String(Math.min(max, Math.max(min, valeur)));
        this.calculer();
    }
}
