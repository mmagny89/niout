import { Controller } from '@hotwired/stimulus';

/*
 * La fenêtre au-dessus de la carte.
 *
 * Un `<dialog>` non modal qui contient un `<turbo-frame id="fenetre">`. Un lien
 * `data-turbo-frame="fenetre"` y charge sa cible sans quitter la carte, un
 * formulaire posté depuis l'intérieur s'y soumet, et le serveur redirige comme
 * il l'a toujours fait : c'est le cadre de la page d'arrivée qui s'affiche.
 *
 * **Non modal, parce que le bouton de cycle est dans la barre.** Fermer pour
 * avancer le temps serait le contraire de ce qu'on veut. Ce que `showModal()`
 * donnait gratuitement, ce contrôleur le rend à la main : Échap ferme, le focus
 * entre dans la fenêtre, et retourne à ce qui l'a ouverte.
 *
 * **L'état est dans l'URL.** À chaque chargement du cadre, le paramètre `ouvre`
 * est mis à jour (`replaceState`) : recharger la page rouvre la fenêtre au même
 * endroit. Les champs `ouvre` des formulaires de la page — le bouton de cycle —
 * suivent, pour que la quinzaine rouvre la fenêtre là où elle était.
 *
 * **La barre se recharge après chaque action** de la fenêtre : le deben et les
 * réserves peuvent avoir changé, et elle est hors du cadre. Et, à la fermeture,
 * si quelque chose a changé, la carte se rafraîchit en entier — cases, signaux,
 * expéditions —, une seule fois.
 */
export default class extends Controller {
    static targets = ['fenetre', 'cadre'];
    static values = { barre: String };

    connect() {
        this.modifiee = false;
        this.origine = null;

        this.surEchap = (evenement) => {
            if (evenement.key === 'Escape' && this.fenetreTarget.open) {
                this.fermer();
            }
        };
        document.addEventListener('keydown', this.surEchap);

        // Le cadre vit dans la fenêtre : on l'écoute là où il est, qu'il ait été
        // rendu par le serveur ou qu'il le soit à l'arrivée.
        this.surChargement = (evenement) => this.cadreCharge(evenement);
        this.surEnvoi = (evenement) => this.actionFaite(evenement);
        this.element.addEventListener('turbo:frame-load', this.surChargement);
        this.element.addEventListener('turbo:submit-end', this.surEnvoi);
    }

    disconnect() {
        document.removeEventListener('keydown', this.surEchap);
        this.element.removeEventListener('turbo:frame-load', this.surChargement);
        this.element.removeEventListener('turbo:submit-end', this.surEnvoi);
    }

    /** Un lien a demandé la fenêtre : on l'ouvre tout de suite, le contenu suit. */
    ouvrir(evenement) {
        if (evenement?.metaKey || evenement?.ctrlKey || evenement?.shiftKey || (evenement?.button ?? 0) !== 0) {
            return;
        }

        this.origine = evenement?.currentTarget ?? document.activeElement;
        this.montrer();
    }

    montrer() {
        if (typeof this.fenetreTarget.show !== 'function') {
            return;
        }

        if (!this.fenetreTarget.open) {
            this.fenetreTarget.show();
        }
    }

    fermer() {
        this.fenetreTarget.close();
        this.mettreAJourLUrl(null);

        // Le focus retourne à ce qui a ouvert la fenêtre : sans cela, il tombe
        // sur le haut du document, et le clavier doit refaire tout le chemin.
        this.origine?.focus?.();
        this.origine = null;

        if (this.modifiee) {
            this.modifiee = false;
            // Une seule fois, à la fermeture : la carte rend ce que la fenêtre a changé.
            window.Turbo?.visit(window.location.href, { action: 'replace' });
        }
    }

    cadreCharge(evenement) {
        if (evenement.target?.id !== 'fenetre') {
            return;
        }

        this.montrer();

        const source = evenement.target.src;
        if (source) {
            const lien = new URL(source, window.location.origin);
            this.mettreAJourLUrl(lien.pathname + lien.search);
        }

        // Le contenu rendu ne s'ouvre pas en tête de page : on y met le focus.
        const titre = this.fenetreTarget.querySelector('#fenetre-titre');
        if (titre) {
            titre.setAttribute('tabindex', '-1');
            titre.focus({ preventScroll: true });
        }
    }

    /** Un formulaire de la fenêtre vient d'aboutir : la barre et la carte sont à rafraîchir. */
    actionFaite(evenement) {
        if (!this.fenetreTarget.contains(evenement.target) || !evenement.detail?.success) {
            return;
        }

        this.modifiee = true;
        this.rechargerLaBarre();
    }

    rechargerLaBarre() {
        const barre = document.getElementById('barre');

        if (!barre || !this.barreValue) {
            return;
        }

        const url = this.barreValue + (this.barreValue.includes('?') ? '&' : '?')
            + 'ouvre=' + encodeURIComponent(this.ouvreCourant() ?? '');

        if (barre.src === new URL(url, window.location.origin).href) {
            barre.reload();
        } else {
            barre.src = url;
        }
    }

    ouvreCourant() {
        return new URL(window.location.href).searchParams.get('ouvre');
    }

    mettreAJourLUrl(chemin) {
        const url = new URL(window.location.href);

        if (chemin) {
            url.searchParams.set('ouvre', chemin);
        } else {
            url.searchParams.delete('ouvre');
        }

        window.history.replaceState(window.history.state, '', url);

        // Le bouton de cycle reprend la fenêtre où elle est.
        document.querySelectorAll('input[name="ouvre"]').forEach((champ) => {
            champ.value = chemin ?? '';
        });
    }
}
