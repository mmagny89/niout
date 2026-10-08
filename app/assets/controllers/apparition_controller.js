import { Controller } from '@hotwired/stimulus';

/*
 * Les éléments `.revele` d'une page apparaissent quand ils entrent à l'écran.
 *
 * **Le contenu ne dépend jamais de ce script** : la CSS ne masque `.revele` que si le
 * navigateur exécute les scripts (`@media (scripting: enabled)`) et si l'on n'a pas demandé
 * moins de mouvement. Sans JavaScript, ou si ce contrôleur ne se charge pas, tout est visible.
 *
 * Les éléments qui entrent ensemble (une rangée de cartes) se décalent un peu : on lit de
 * gauche à droite, l'apparition aussi.
 */
export default class extends Controller {
    connect() {
        const elements = Array.from(this.element.querySelectorAll('.revele'));

        if (!('IntersectionObserver' in window)) {
            elements.forEach((element) => element.classList.add('apparu'));
            return;
        }

        this.observateur = new IntersectionObserver((entrees) => {
            entrees
                .filter((entree) => entree.isIntersecting)
                .forEach((entree, rang) => {
                    entree.target.style.transitionDelay = `${Math.min(rang, 6) * 90}ms`;
                    entree.target.classList.add('apparu');
                    this.observateur.unobserve(entree.target);
                });
        }, { threshold: 0.12 });

        elements.forEach((element) => this.observateur.observe(element));
    }

    disconnect() {
        this.observateur?.disconnect();
    }
}
