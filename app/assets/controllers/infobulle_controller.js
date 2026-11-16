import { Controller } from '@hotwired/stimulus';

/*
 * Une infobulle posée sur un élément : au survol et au focus clavier, jamais au seul toucher
 * (un toucher suit le lien ; le détail est de toute façon dans la fiche du jour).
 *
 * **Elle vit sur `<body>`, pas dans la fenêtre.** La fenêtre défile et rogne : une bulle posée
 * dedans serait coupée au bord du panneau. On en montre donc une copie, en `position: fixed`,
 * placée d'après la boîte de l'ancre puis ramenée dans l'écran. Le texte reste dans la page, lié
 * par `aria-describedby` : le lecteur d'écran n'a pas besoin de la copie.
 */
const ECART = 8;
const MARGE = 8;

export default class extends Controller {
    static targets = ['ancre', 'contenu'];

    disconnect() {
        this.cacher();
    }

    montrer() {
        this.cacher();

        const bulle = this.contenuTarget.cloneNode(true);
        bulle.removeAttribute('id');
        bulle.removeAttribute('data-infobulle-target');
        bulle.removeAttribute('role');
        bulle.setAttribute('aria-hidden', 'true');
        bulle.hidden = false;
        bulle.style.position = 'fixed';
        bulle.style.zIndex = '3000';
        bulle.style.left = '0';
        bulle.style.top = '0';
        bulle.style.visibility = 'hidden';
        document.body.appendChild(bulle);

        const ancre = this.ancreTarget.getBoundingClientRect();
        const taille = bulle.getBoundingClientRect();
        // Sous l'ancre ; au-dessus si l'écran manque en bas.
        const dessous = ancre.bottom + ECART + taille.height <= window.innerHeight - MARGE;
        const haut = dessous ? ancre.bottom + ECART : Math.max(MARGE, ancre.top - ECART - taille.height);
        const gauche = Math.min(
            window.innerWidth - MARGE - taille.width,
            Math.max(MARGE, ancre.left + ancre.width / 2 - taille.width / 2),
        );

        bulle.style.left = `${gauche}px`;
        bulle.style.top = `${haut}px`;
        bulle.style.visibility = '';
        this.bulle = bulle;
    }

    cacher() {
        if (this.bulle) {
            this.bulle.remove();
            this.bulle = null;
        }
    }
}
