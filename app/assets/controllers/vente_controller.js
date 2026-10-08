import { Controller } from '@hotwired/stimulus';

/*
 * Un lot à l'étal : combien rapportera ce que je vais écouler ?
 *
 * Le gain affiché est `quantité × prix unitaire` — le même calcul que le serveur
 * (`Marche::vendre()`), qui reste seul juge : le plafond de la place et l'arrondi s'y
 * décident, ce contrôleur ne fait que montrer. « Tout » remplit la plus grande quantité
 * que la place absorbe encore (`data-vente-max-value`), celle que le gabarit propose déjà.
 *
 * Sans JavaScript, le formulaire marche tel quel : le gain est un confort.
 */
export default class extends Controller {
    static targets = ['quantite', 'gain'];
    static values = { prix: Number, max: Number };

    connect() {
        this.calculer();
    }

    calculer() {
        const quantite = Math.max(0, Number.parseInt(this.quantiteTarget.value, 10) || 0);
        this.gainTarget.textContent = `≈ ${quantite * this.prixValue} deben`;
    }

    tout() {
        this.quantiteTarget.value = Math.max(1, this.maxValue);
        this.calculer();
    }
}
