import { Controller } from '@hotwired/stimulus';

/*
 * Masque les réglages propres au mode Aventure quand la Campagne est choisie.
 *
 * Amélioration progressive : sans JavaScript, le bloc reste visible et le
 * serveur ignore ces champs en campagne. Rien ne casse.
 */
export default class extends Controller {
    static targets = ['mode', 'reglagesAventure'];

    connect() {
        this.basculer();
        this.modeTarget.addEventListener('change', () => this.basculer());
    }

    basculer() {
        const choisi = this.modeTarget.querySelector('input[type="radio"]:checked');
        const estAventure = choisi?.value === 'aventure';

        this.reglagesAventureTarget.hidden = !estAventure;
    }
}
