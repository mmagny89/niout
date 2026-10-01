import { Controller } from '@hotwired/stimulus';

/*
 * La fenêtre de la cité, au-dessus de la carte.
 *
 * Cliquer la tuile de la ville ouvre ce qu'on y a bâti, sans quitter le
 * territoire : un carré par bâtiment, et un clic sur l'un d'eux mène à son
 * onglet.
 *
 * C'est un `<dialog>` natif, ouvert par `showModal()` : le navigateur pose le
 * piège à focus, l'inertie du reste de la page et la touche Échap — trois
 * choses qu'une fenêtre faite à la main oublie toujours l'une ou l'autre.
 *
 * **Sans JavaScript, la tuile reste un lien** vers la page de la cité
 * (`app_partie_cite`) : c'est la même liste, sur un écran à elle. Ce
 * contrôleur n'ajoute que l'ouverture en surimpression, et laisse passer le
 * clic modifié (Ctrl, Cmd, clic du milieu) pour qu'on puisse toujours ouvrir la
 * cité dans un autre onglet.
 */
export default class extends Controller {
    static targets = ['fenetre'];

    ouvrir(evenement) {
        if (evenement.metaKey || evenement.ctrlKey || evenement.shiftKey || evenement.button !== 0) {
            return;
        }

        if (!this.hasFenetreTarget || typeof this.fenetreTarget.showModal !== 'function') {
            return;
        }

        evenement.preventDefault();
        this.fenetreTarget.showModal();
    }

    fermer() {
        this.fenetreTarget.close();
    }

    /** Un clic sur le fond — le `<dialog>` lui-même, hors de son contenu — ferme. */
    surFond(evenement) {
        if (evenement.target === this.fenetreTarget) {
            this.fermer();
        }
    }
}
