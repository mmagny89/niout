import { Controller } from '@hotwired/stimulus';

/*
 * « Retour en haut » d'une fenêtre dont le contenu est long.
 *
 * Posé sur le **conteneur qui défile** : le bouton, dernier enfant de ce
 * conteneur, y reste collé en bas (`sticky`) et n'apparaît qu'une fois le contenu
 * descendu au-delà d'un seuil. La fenêtre elle-même ne défile jamais — c'est son
 * intérieur qui le fait —, d'où un contrôleur par fenêtre plutôt qu'un bouton
 * global.
 *
 * Au clic, on remonte (sans animation si l'utilisateur préfère moins de
 * mouvement) et le focus retourne au titre : au clavier ou au lecteur d'écran,
 * rester sur un bouton qui vient de disparaître ferait perdre sa place.
 */
export default class extends Controller {
    static targets = ['bouton'];
    static values = { seuil: { type: Number, default: 320 } };

    connect() {
        this.defiler();
    }

    defiler() {
        if (!this.hasBoutonTarget) {
            return;
        }

        this.boutonTarget.hidden = this.element.scrollTop < this.seuilValue;
    }

    remonter() {
        const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.element.scrollTo({ top: 0, behavior: reduit ? 'auto' : 'smooth' });

        const titre = this.element.querySelector('#fenetre-titre');
        if (titre) {
            if (!titre.hasAttribute('tabindex')) {
                titre.setAttribute('tabindex', '-1');
            }
            titre.focus({ preventScroll: true });
        }
    }
}
