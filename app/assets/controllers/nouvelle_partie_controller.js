import { Controller } from '@hotwired/stimulus';

/*
 * La création d'une partie : ce que le mode choisi laisse régler, et à quoi ressemblent les
 * réglages.
 *
 * Amélioration progressive : sans JavaScript, le bloc des réglages reste visible, le serveur
 * ignore ces champs en campagne, et l'aperçu comme les dangers sont simplement absents.
 * Rien de ce que ce contrôleur dessine n'est soumis — les champs du formulaire sont la seule source.
 */
export default class extends Controller {
    static targets = ['mode', 'reglagesAventure', 'difficulte', 'danger', 'taille', 'apercu'];

    connect() {
        this.basculer();
        this.modeTarget.addEventListener('change', () => this.basculer());
        this.reglages();
    }

    basculer() {
        const choisi = this.modeTarget.querySelector('input[type="radio"]:checked');
        const estAventure = choisi?.value === 'aventure';

        this.reglagesAventureTarget.hidden = !estAventure;
    }

    /** Redessine les dangers allumés et l'aperçu de la grille d'après les deux listes. */
    reglages() {
        if (this.hasDifficulteTarget && this.hasDangerTarget) {
            const options = Array.from(this.difficulteTarget.options);
            // Le premier cran est « aucun danger » (niveau 0) : il n'a pas de signe, et le niveau
            // choisi est le rang de l'option — un signe allumé par cran au-dessus de zéro.
            const niveau = Math.max(0, options.findIndex((option) => option.selected));

            this.dangerTarget.replaceChildren(...options.slice(1).map((_, rang) => {
                const goutte = document.createElement('span');
                goutte.className = rang < niveau ? 'danger-allume' : 'danger-eteint';
                goutte.textContent = '▲';
                return goutte;
            }));
        }

        if (this.hasTailleTarget && this.hasApercuTarget) {
            const cote = Number.parseInt(this.tailleTarget.value, 10) || 8;
            this.apercuTarget.style.setProperty('--cote', String(cote));
            this.apercuTarget.replaceChildren(...Array.from({ length: cote * cote }, (_, rang) => {
                const case_ = document.createElement('span');
                case_.className = (Math.floor(rang / cote) + (rang % cote)) % 2 === 0 ? 'case-a' : 'case-b';
                return case_;
            }));
        }
    }
}
