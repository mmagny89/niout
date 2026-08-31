import { Controller } from '@hotwired/stimulus';

/*
 * La carte tient dans l'écran, quelle que soit sa taille.
 *
 * Une grille du Sinaï fait 8 × 8 cases, soit près de 1 600 pixels de large :
 * sans mise à l'échelle, le joueur perdait son territoire hors de la fenêtre et
 * devait faire défiler pour comparer deux cases. On calcule donc le facteur qui
 * fait entrer la grille entière dans le panneau, et l'on s'y tient — le joueur
 * peut ensuite s'approcher s'il veut lire une tuile.
 *
 * `transform: scale()` plutôt qu'un redimensionnement des tuiles : la couche
 * cliquable subit exactement la même transformation que l'image, donc les
 * losanges continuent de tomber juste. Redimensionner l'une sans l'autre est le
 * défaut classique de ce genre d'écran.
 */
export default class extends Controller {
    static targets = ['grille', 'facteur'];
    static values = { min: Number, max: Number, pas: Number };

    connect() {
        this.zoom = null;
        this.ajuster = this.ajuster.bind(this);
        window.addEventListener('resize', this.ajuster);
        this.ajuster();
    }

    disconnect() {
        window.removeEventListener('resize', this.ajuster);
    }

    /** Le facteur qui fait entrer la grille entière, jamais au-delà de 1 :
     *  agrandir une image de tuile la rendrait floue. */
    ajustement() {
        const dispo = this.element.getBoundingClientRect();
        const largeur = this.grilleTarget.offsetWidth;
        const hauteur = this.grilleTarget.offsetHeight;

        if (largeur === 0 || hauteur === 0) {
            return 1;
        }

        return Math.min(1, dispo.width / largeur, dispo.height / hauteur);
    }

    ajuster() {
        this.appliquer(this.zoom ?? this.ajustement());
    }

    approcher() {
        this.appliquer((this.zoom ?? this.ajustement()) + this.pasValue);
    }

    eloigner() {
        this.appliquer((this.zoom ?? this.ajustement()) - this.pasValue);
    }

    revenir() {
        this.zoom = null;
        this.appliquer(this.ajustement());
    }

    appliquer(facteur) {
        const borne = Math.min(this.maxValue, Math.max(this.minValue, facteur));

        this.zoom = borne === this.ajustement() ? this.zoom : borne;
        this.grilleTarget.style.transform = `scale(${borne})`;

        if (this.hasFacteurTarget) {
            this.facteurTarget.textContent = `${Math.round(borne * 100)} %`;
        }
    }
}
