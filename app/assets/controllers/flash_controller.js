import { Controller } from '@hotwired/stimulus';

/*
 * Les messages de la partie : un bouton pour les faire taire.
 *
 * Dans la coque du jeu, ils flottent au-dessus du contenu et ne disparaissent
 * qu'à la navigation suivante. Trois messages empilés recouvraient alors le
 * haut du panneau ouvert, sans qu'on puisse rien y faire — et l'on ne veut pas
 * avancer d'une quinzaine juste pour retrouver son écran.
 *
 * Le message reste dans le document jusqu'au geste : rien ne s'efface tout
 * seul. Un message qui s'évanouit au bout de quelques secondes est un message
 * qu'on n'a pas fini de lire, et le journal d'une quinzaine est long.
 *
 * **Le récapitulatif d'une quinzaine se replie sur téléphone.** Il recouvrait presque toute la ville
 * vue d'en haut — et le film qui démarre derrière, que personne ne voyait. Sous `md`, seul son
 * en-tête reste, avec un bouton pour déplier ; au-delà, il reste ouvert comme avant. Sans JavaScript,
 * il est entier : replier n'est jamais nécessaire pour lire.
 */
export default class extends Controller {
    static targets = ['corps', 'bascule'];

    connect() {
        if (this.hasCorpsTarget && window.matchMedia('(max-width: 767px)').matches) {
            this.regler(false);
            this.passerSousLaBarre();
        }
    }

    /**
     * Les messages flottent à 6 rem du haut, ce qui suppose une barre d'une rangée. Sur téléphone elle
     * en fait deux ou trois : les pastilles recouvraient le titre du récapitulatif. On le pose donc
     * sous la barre **telle qu'elle est**, et jamais plus haut que le décalage d'origine.
     */
    passerSousLaBarre() {
        const barre = document.querySelector('header');
        const pile = this.element.parentElement;

        if (barre && pile) {
            pile.style.top = `${Math.max(96, Math.round(barre.getBoundingClientRect().bottom) + 8)}px`;
        }
    }

    fermer() {
        this.element.remove();
    }

    basculer() {
        this.regler(this.corpsTarget.hidden);
    }

    regler(ouvert) {
        this.corpsTarget.hidden = !ouvert;

        if (this.hasBasculeTarget) {
            this.basculeTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
            this.basculeTarget.textContent = ouvert ? 'Replier' : 'Détails';
        }
    }
}
