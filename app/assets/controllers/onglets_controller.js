import { Controller } from '@hotwired/stimulus';

/*
 * Les onglets de l'écran de ville.
 *
 * La ville portait neuf sections empilées, soit plusieurs écrans de haut : pour
 * comparer sa masse salariale et son étal, il fallait faire défiler et retenir.
 * Les sections deviennent des onglets, et l'écran cesse d'être un document.
 *
 * **Tous les panneaux restent dans le document**, seulement masqués : la page
 * est rendue d'un bloc par le serveur, changer d'onglet ne demande donc aucun
 * aller-retour, et le contenu reste lisible par une recherche de page comme par
 * un lecteur d'écran qui parcourt le document.
 */
export default class extends Controller {
    static targets = ['onglet', 'panneau'];
    static classes = ['actif', 'inactif'];

    connect() {
        this.montrer(this.ongletTargets.findIndex((o) => o.dataset.ongletActif === 'true') ?? 0);
    }

    choisir(evenement) {
        this.montrer(this.ongletTargets.indexOf(evenement.currentTarget));
    }

    /** Flèches gauche/droite entre onglets : c'est ce qu'un lecteur d'écran
     *  annonce et ce qu'un clavier attend d'une barre d'onglets. */
    naviguer(evenement) {
        const touches = { ArrowRight: 1, ArrowLeft: -1 };
        const pas = touches[evenement.key];

        if (pas === undefined) {
            return;
        }

        evenement.preventDefault();
        const total = this.ongletTargets.length;
        const suivant = (this.ongletTargets.indexOf(evenement.currentTarget) + pas + total) % total;

        this.montrer(suivant);
        this.ongletTargets[suivant].focus();
    }

    montrer(rang) {
        const choisi = rang < 0 ? 0 : rang;

        this.ongletTargets.forEach((onglet, index) => {
            const actif = index === choisi;
            onglet.setAttribute('aria-selected', actif ? 'true' : 'false');
            onglet.setAttribute('tabindex', actif ? '0' : '-1');
            onglet.classList.toggle(this.actifClass, actif);
            onglet.classList.toggle(this.inactifClass, !actif);
        });

        this.panneauTargets.forEach((panneau, index) => {
            panneau.hidden = index !== choisi;
        });
    }
}
