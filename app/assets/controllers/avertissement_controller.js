import { Controller } from '@hotwired/stimulus';

/*
 * Le bandeau d'avertissement : se souvenir qu'on l'a replié.
 *
 * Le repli lui-même est celui de `<details>`, natif : il fonctionne sans
 * JavaScript, et c'est délibéré. Ce contrôleur n'ajoute que la mémoire du
 * choix, d'une page à l'autre — sans elle, le bandeau se rouvre à chaque
 * navigation et l'information devient une importunité.
 *
 * Le choix vit dans le navigateur de la personne, pas en base : il ne regarde
 * qu'elle, ne concerne pas sa partie, et n'a rien à faire dans un compte.
 *
 * Les accès à localStorage sont gardés : un navigateur en navigation privée,
 * ou réglé pour refuser le stockage, lève une exception à la lecture comme à
 * l'écriture. Le bandeau doit alors se comporter comme si rien n'était
 * mémorisé, jamais casser la page autour de lui.
 */
export default class extends Controller {
    static values = { cle: { type: String, default: 'niout.avertissement.equilibrage' } };

    connect() {
        if ('replie' === this.#lire()) {
            this.element.open = false;
        }

        this.#surBascule = () => this.#ecrire(this.element.open ? 'ouvert' : 'replie');
        this.element.addEventListener('toggle', this.#surBascule);
    }

    disconnect() {
        this.element.removeEventListener('toggle', this.#surBascule);
    }

    #surBascule;

    #lire() {
        try {
            return window.localStorage.getItem(this.cleValue);
        } catch {
            return null;
        }
    }

    #ecrire(valeur) {
        try {
            window.localStorage.setItem(this.cleValue, valeur);
        } catch {
            // Rien à faire : le bandeau se rouvrira, ce qui reste correct.
        }
    }
}
