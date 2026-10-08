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
 * **Le bouton retour du navigateur ferme la fenêtre**, comme on s'y attend d'une
 * fenêtre : ouvrir depuis la carte ajoute une entrée à l'historique
 * (`pushState`), naviguer d'un bâtiment à l'autre la remplace (`replaceState`),
 * et revenir en arrière — ou en avant — referme ou rouvre la fenêtre. Fermer par
 * la croix ou Échap défait l'entrée qu'on avait ajoutée, de sorte que la
 * fermeture ne laisse aucune trace. Une page rechargée avec la fenêtre déjà
 * ouverte n'a pas d'entrée à elle : le retour quitte alors la carte, ce qui est
 * l'attendu d'un rechargement.
 *
 * **Un clic en dehors de la fenêtre la ferme** — sur le fond de la carte ou de la
 * ville, pas sur ce qui agit déjà : un lien (une autre case, un bâtiment), un
 * bouton, un champ font leur travail, et la barre de jeu, hors de ce contrôleur,
 * reste utilisable fenêtre ouverte. Un clic qui termine un **glissement** de la
 * carte n'en est pas un : on mesure le déplacement entre l'appui et le relâché.
 *
 * **Au clavier et au lecteur d'écran** : à chaque chargement du cadre, le focus
 * entre sur le titre de la fenêtre (`#fenetre-titre`), que le lecteur d'écran
 * annonce — c'est l'« ouverture » d'une fenêtre non modale. À la fermeture, le
 * focus retourne à ce qui l'a ouverte : une tuile de la carte, ou une pastille
 * de la barre. La barre étant hors de ce contrôleur et rechargée après chaque
 * action, l'élément d'origine peut avoir été remplacé : on le retrouve alors par
 * son adresse. Sur un téléphone, le rail de la ville défile : le bâtiment ouvert
 * est ramené dans la vue.
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
        // Vrai quand l'ouverture a ajouté une entrée d'historique, que la
        // fermeture doit défaire.
        this.empilee = false;
        // Vrai quand un `history.back()` vient de nous, et que le `popstate`
        // qu'il provoque n'est donc pas à traiter une seconde fois.
        this.retourInterne = false;

        this.surHistorique = () => this.historiqueChange();
        window.addEventListener('popstate', this.surHistorique);

        this.surEchap = (evenement) => {
            if (evenement.key === 'Escape' && this.fenetreTarget.open) {
                this.fermer();
            }
        };
        document.addEventListener('keydown', this.surEchap);

        // Le cadre vit dans la fenêtre : on l'écoute là où il est, qu'il ait été
        // rendu par le serveur ou qu'il le soit à l'arrivée.
        // Un appui puis un relâché au même endroit, hors de la fenêtre et hors de
        // tout ce qui agit : on ferme.
        this.surAppui = (evenement) => {
            this.appui = { x: evenement.clientX, y: evenement.clientY };
        };
        this.surClicDehors = (evenement) => this.clicDehors(evenement);
        this.element.addEventListener('pointerdown', this.surAppui, true);
        this.element.addEventListener('click', this.surClicDehors);

        // Un lien de la barre — une pastille de signal — ouvre la fenêtre sans
        // être dans ce contrôleur : on retient d'où l'on vient pour y rendre le
        // focus, comme pour une tuile.
        this.surClic = (evenement) => this.retenirLOrigine(evenement);
        document.addEventListener('click', this.surClic, true);

        this.surChargement = (evenement) => this.cadreCharge(evenement);
        this.surEnvoi = (evenement) => this.actionFaite(evenement);
        this.element.addEventListener('turbo:frame-load', this.surChargement);
        this.element.addEventListener('turbo:submit-end', this.surEnvoi);

        // Une page rendue avec la fenêtre déjà ouverte : sa forme se lit dans son contenu.
        this.synchroniserLaForme();
    }

    disconnect() {
        window.removeEventListener('popstate', this.surHistorique);
        document.removeEventListener('keydown', this.surEchap);
        document.removeEventListener('click', this.surClic, true);
        this.element.removeEventListener('pointerdown', this.surAppui, true);
        this.element.removeEventListener('click', this.surClicDehors);
        this.element.removeEventListener('turbo:frame-load', this.surChargement);
        this.element.removeEventListener('turbo:submit-end', this.surEnvoi);
    }

    /** La forme de la fenêtre est celle du contenu qu'elle porte : feuille à droite, ou fenêtre large. */
    synchroniserLaForme() {
        this.fenetreTarget.dataset.forme = this.fenetreTarget.querySelector('[data-forme]')?.dataset.forme ?? 'large';
    }

    /** Un lien a demandé la fenêtre : on l'ouvre tout de suite, le contenu suit. */
    ouvrir(evenement) {
        if (evenement?.metaKey || evenement?.ctrlKey || evenement?.shiftKey || (evenement?.button ?? 0) !== 0) {
            return;
        }

        this.origine = evenement?.currentTarget ?? document.activeElement;

        // **La forme se décide avant l'ouverture, pas à l'arrivée du contenu.** La fenêtre apparaît
        // tout de suite et le contenu suit : tant qu'il n'est pas là, le cadre est vide — ou garde
        // l'écran précédent —, sans le `data-forme` que la CSS lisait pour poser la feuille à
        // droite. La fenêtre s'ouvrait donc large et centrée, puis sautait à droite. Le lien dit
        // la forme qu'il attend (`data-fenetre-forme`) ; le chargement la confirmera.
        this.fenetreTarget.dataset.forme = evenement?.currentTarget?.dataset?.fenetreForme ?? 'large';
        this.montrer();
    }

    /** Un clic hors de la fenêtre, sur du vide : elle se ferme. */
    clicDehors(evenement) {
        if (!this.fenetreTarget.open) {
            return;
        }

        const cible = evenement.target;

        if (this.fenetreTarget.contains(cible) || cible.closest?.('a, button, input, select, textarea, label, summary')) {
            return;
        }

        // La fin d'un glissement de la carte n'est pas un clic.
        if (this.appui && Math.hypot(evenement.clientX - this.appui.x, evenement.clientY - this.appui.y) > 6) {
            return;
        }

        this.fermer();
    }

    /** Un lien qui cible la fenêtre, hors d'elle : c'est lui qui la rouvrira. */
    retenirLOrigine(evenement) {
        const lien = evenement.target?.closest?.('a[data-turbo-frame="fenetre"]');

        if (lien && !this.fenetreTarget.contains(lien)) {
            this.origine = lien;
            this.adresseDeLOrigine = lien.getAttribute('href');
        }
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
        this.refermer();

        if (this.empilee) {
            // L'ouverture avait ajouté une entrée : on la défait, pour que
            // « retour » ne rouvre pas une fenêtre qu'on vient de fermer. Le
            // rafraîchissement attend que l'adresse ait fini de reculer : le
            // lancer tout de suite visiterait encore l'adresse « ouverte », et
            // rouvrirait la fenêtre qu'on vient de fermer.
            this.empilee = false;
            this.retourInterne = true;
            window.history.back();

            return;
        }

        this.mettreAJourLUrl(null);
        this.rafraichirSiBesoin();
    }

    /** Ferme la fenêtre elle-même, sans toucher à l'historique ni à la carte. */
    refermer() {
        if (this.fenetreTarget.open) {
            this.fenetreTarget.close();
        }

        // Le focus retourne à ce qui a ouvert la fenêtre : sans cela, il tombe
        // sur le haut du document, et le clavier doit refaire tout le chemin.
        this.rendreLeFocus();
    }

    /**
     * L'origine a pu être remplacée — la barre se recharge, la carte se
     * rafraîchit — : on la retrouve par son adresse plutôt que de perdre le
     * focus en haut du document.
     */
    rendreLeFocus() {
        let cible = this.origine;

        if (cible && !cible.isConnected && this.adresseDeLOrigine) {
            cible = document.querySelector(`a[href="${CSS.escape(this.adresseDeLOrigine)}"]`);
        }

        cible?.focus?.();
        this.origine = null;
        this.adresseDeLOrigine = null;
    }

    /** Une seule fois, à la fermeture : la carte rend ce que la fenêtre a changé. */
    rafraichirSiBesoin() {
        if (!this.modifiee) {
            return;
        }

        this.modifiee = false;
        window.Turbo?.visit(window.location.href, { action: 'replace' });
    }

    /**
     * « Retour » ou « Suivant » dans le navigateur : l'adresse dit si la fenêtre
     * doit être ouverte, et sur quoi.
     */
    historiqueChange() {
        if (this.retourInterne) {
            this.retourInterne = false;
            this.rafraichirSiBesoin();

            return;
        }

        const ouvre = this.ouvreCourant();

        if (!ouvre && this.fenetreTarget.open) {
            this.empilee = false;
            this.refermer();
            this.rafraichirSiBesoin();
        } else if (ouvre && !this.fenetreTarget.open && this.hasCadreTarget) {
            // Retour vers une fenêtre ouverte : on recharge son contenu.
            this.empilee = true;
            this.cadreTarget.src = ouvre;
        }
    }

    cadreCharge(evenement) {
        if (evenement.target?.id !== 'fenetre') {
            return;
        }

        // La forme réelle est celle du contenu arrivé : elle corrige l'attente du lien, et suit
        // la navigation dans la fenêtre (de la case aux expéditions, de la ville à une case).
        this.synchroniserLaForme();

        this.montrer();

        const source = evenement.target.src;
        if (source) {
            const lien = new URL(source, window.location.origin);
            this.mettreAJourLUrl(lien.pathname + lien.search);
        }

        // Sur un téléphone le rail défile : le bâtiment ouvert doit être visible.
        this.fenetreTarget.querySelector('nav [aria-current="page"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'center' });

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

    /** L'adresse dit-elle déjà qu'une fenêtre est ouverte ? */
    get estOuverteDansLUrl() {
        return this.ouvreCourant() !== null;
    }

    mettreAJourLUrl(chemin) {
        const url = new URL(window.location.href);

        if (chemin) {
            url.searchParams.set('ouvre', chemin);
        } else {
            url.searchParams.delete('ouvre');
        }

        // Ouvrir ajoute une entrée d'historique, naviguer dans la fenêtre la
        // remplace : « retour » referme la fenêtre, il ne remonte pas ses étapes.
        if (chemin && !this.estOuverteDansLUrl && !this.empilee) {
            window.history.pushState({ fenetre: true }, '', url);
            this.empilee = true;
        } else {
            window.history.replaceState(window.history.state, '', url);
        }

        // Le bouton de cycle reprend la fenêtre où elle est.
        document.querySelectorAll('input[name="ouvre"]').forEach((champ) => {
            champ.value = chemin ?? '';
        });
    }
}
