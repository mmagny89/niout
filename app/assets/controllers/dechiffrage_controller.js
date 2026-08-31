import { Controller } from '@hotwired/stimulus';

/*
 * Remettre les signes d'une inscription dans l'ordre.
 *
 * **Le clavier d'abord, le glisser-déposer par-dessus.** Toute la mécanique
 * passe par un seul geste — « placer » un signe, « retirer » un signe —, et le
 * glisser-déposer ne fait qu'appeler ces mêmes actions. Un jeu qui n'enseigne
 * qu'à la souris n'enseigne pas à tout le monde ; et une interaction bâtie
 * autour du seul `dragstart` est inutilisable au clavier, sans qu'aucun test
 * ne le signale.
 *
 * Le contrôleur ne juge rien : il compose une réponse et la met dans un champ
 * caché. C'est le serveur qui dit si la lecture est juste — sinon la réponse
 * serait dans la page, et l'énigme n'en serait plus une.
 */
export default class extends Controller {
    static targets = ['reserve', 'case', 'reponse', 'valider'];

    connect() {
        this.refleter();
    }

    /** Place un signe dans la première case libre. */
    placer(evenement) {
        const jeton = evenement.currentTarget;
        const libre = this.caseTargets.find((emplacement) => !emplacement.dataset.signe);

        if (!libre || !jeton.dataset.signe) {
            return;
        }

        libre.dataset.signe = jeton.dataset.signe;
        libre.textContent = jeton.dataset.glyphe;
        libre.setAttribute('aria-label', `Case ${this.caseTargets.indexOf(libre) + 1} : ${jeton.dataset.libelle}`);
        jeton.hidden = true;
        libre.dataset.jeton = this.jetonTargets().indexOf(jeton);

        this.refleter();
    }

    /** Retire le signe d'une case et le rend à la réserve. */
    retirer(evenement) {
        const emplacement = evenement.currentTarget;

        if (!emplacement.dataset.signe) {
            return;
        }

        const jeton = this.jetonTargets()[Number(emplacement.dataset.jeton)];

        if (jeton) {
            jeton.hidden = false;
        }

        delete emplacement.dataset.signe;
        delete emplacement.dataset.jeton;
        emplacement.textContent = '';
        emplacement.setAttribute('aria-label', `Case ${this.caseTargets.indexOf(emplacement) + 1}, vide`);

        this.refleter();
    }

    /* --- Glisser-déposer : une commodité, jamais le seul chemin. --- */

    prendre(evenement) {
        evenement.dataTransfer.setData('text/plain', String(this.jetonTargets().indexOf(evenement.currentTarget)));
        evenement.dataTransfer.effectAllowed = 'move';
    }

    survoler(evenement) {
        evenement.preventDefault();
    }

    deposer(evenement) {
        evenement.preventDefault();
        const jeton = this.jetonTargets()[Number(evenement.dataTransfer.getData('text/plain'))];

        if (jeton && !jeton.hidden) {
            this.placer({ currentTarget: jeton });
        }
    }

    jetonTargets() {
        return Array.from(this.reserveTarget.querySelectorAll('[data-signe]'));
    }

    /** Recopie l'état des cases dans le champ soumis, et ferme le bouton tant
     *  que l'inscription n'est pas complète. */
    refleter() {
        const signes = this.caseTargets.map((emplacement) => emplacement.dataset.signe ?? '');
        const complet = signes.every((signe) => signe !== '');

        this.reponseTarget.value = complet ? signes.join(',') : '';

        if (this.hasValiderTarget) {
            this.validerTarget.disabled = !complet;
        }
    }
}
