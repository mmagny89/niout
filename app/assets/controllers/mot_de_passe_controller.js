import { Controller } from '@hotwired/stimulus';

/*
 * Le petit œil des champs de mot de passe.
 *
 * Une saisie masquée se tape à l'aveugle : sur un mot de passe long, une faute
 * de frappe ne se distingue pas d'un mot de passe faux, et l'on recommence
 * sans savoir ce qu'on corrige. Le bouton bascule le champ en clair le temps
 * de relire.
 *
 * Le bouton est rendu masqué et n'apparaît qu'une fois le contrôleur
 * connecté : sans JavaScript, le champ reste une saisie masquée ordinaire
 * plutôt qu'un bouton mort.
 */
export default class extends Controller {
    static targets = ['champ', 'bouton', 'oeil', 'oeilBarre'];

    connect() {
        this.boutonTarget.hidden = false;
    }

    basculer() {
        const masque = this.champTarget.type === 'password';

        this.champTarget.type = masque ? 'text' : 'password';
        this.boutonTarget.setAttribute('aria-pressed', masque ? 'true' : 'false');
        this.boutonTarget.setAttribute(
            'aria-label',
            masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
        );
        this.oeilTarget.hidden = masque;
        this.oeilBarreTarget.hidden = !masque;

        // Le focus revient à la saisie : basculer l'affichage ne doit pas
        // coûter un aller-retour au clavier pour reprendre la frappe.
        this.champTarget.focus();
    }
}
