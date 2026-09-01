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
 */
export default class extends Controller {
    fermer() {
        this.element.remove();
    }
}
