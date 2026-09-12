import { Controller } from '@hotwired/stimulus';

/*
 * La carte : elle tient dans l'écran, et se manipule au doigt.
 *
 * Une grille du Sinaï fait 8 × 8 cases, soit près de 1 600 pixels de large :
 * sans mise à l'échelle, le joueur perdait son territoire hors de la fenêtre.
 * On calcule donc le facteur qui fait entrer la grille entière, et l'on s'y
 * tient tant que le joueur n'a rien demandé d'autre.
 *
 * `transform` plutôt qu'un redimensionnement des tuiles : la couche cliquable
 * subit exactement la même transformation que l'image, donc les losanges
 * continuent de tomber juste. Redimensionner l'une sans l'autre est le défaut
 * classique de ce genre d'écran.
 *
 * Trois gestes, tous par « pointer events » — une seule implémentation pour la
 * souris, le doigt et le stylet, là où `touchstart` et `mousedown` séparés
 * finissent toujours par diverger :
 *
 *   - un doigt qui glisse déplace la carte ;
 *   - deux doigts qui s'écartent l'approchent, autour du point qu'ils tiennent
 *     et non du centre de l'écran — sans quoi la case qu'on regarde s'enfuit ;
 *   - la molette approche aussi, autour du curseur.
 *
 * **Le déplacement et la sélection se disputent le même geste.** Les cases sont
 * des liens : sans précaution, chaque glissement finit par en ouvrir un. On
 * mesure donc la distance parcourue, et au-delà d'un seuil on avale le clic
 * qui suit — en phase de capture, avant qu'il n'atteigne le lien. En deçà, on
 * ne touche à rien : un vrai clic reste un vrai clic, au doigt comme à la
 * souris comme au clavier.
 *
 * Le panneau porte `touch-none` : sans lui, le navigateur happe le glissement
 * pour faire défiler la page et le pincement pour zoomer le document entier.
 */
export default class extends Controller {
    static targets = ['grille', 'facteur', 'cadre'];
    static values = { min: Number, max: Number, pas: Number };

    /** Au-delà de ce déplacement, le geste est un glissement, pas un clic. */
    static SEUIL_DE_GLISSEMENT = 8;

    connect() {
        this.zoom = null;
        this.tx = 0;
        this.ty = 0;
        this.pointeurs = new Map();
        this.distanceParcourue = 0;
        this.ecartInitial = null;

        this.ajuster = this.ajuster.bind(this);
        this.surPointerDown = this.surPointerDown.bind(this);
        this.surPointerMove = this.surPointerMove.bind(this);
        this.surPointerUp = this.surPointerUp.bind(this);
        this.surClic = this.surClic.bind(this);
        this.surMolette = this.surMolette.bind(this);

        window.addEventListener('resize', this.ajuster);
        this.element.addEventListener('pointerdown', this.surPointerDown);
        this.element.addEventListener('pointermove', this.surPointerMove);
        this.element.addEventListener('pointerup', this.surPointerUp);
        this.element.addEventListener('pointercancel', this.surPointerUp);
        // Capture : il faut avaler le clic avant qu'il n'atteigne le lien.
        this.element.addEventListener('click', this.surClic, true);
        this.element.addEventListener('wheel', this.surMolette, { passive: false });

        this.ajuster();
    }

    disconnect() {
        window.removeEventListener('resize', this.ajuster);
        this.element.removeEventListener('pointerdown', this.surPointerDown);
        this.element.removeEventListener('pointermove', this.surPointerMove);
        this.element.removeEventListener('pointerup', this.surPointerUp);
        this.element.removeEventListener('pointercancel', this.surPointerUp);
        this.element.removeEventListener('click', this.surClic, true);
        this.element.removeEventListener('wheel', this.surMolette);
    }

    // — Gestes ————————————————————————————————————————————————————————————

    surPointerDown(evenement) {
        this.pointeurs.set(evenement.pointerId, { x: evenement.clientX, y: evenement.clientY });

        if (1 === this.pointeurs.size) {
            this.distanceParcourue = 0;
        }

        if (2 === this.pointeurs.size) {
            this.ecartInitial = this.ecartEntrePointeurs();
            this.echelleInitiale = this.facteurCourant();
        }

        this.animer(false);
    }

    surPointerMove(evenement) {
        const precedent = this.pointeurs.get(evenement.pointerId);

        if (!precedent) {
            return;
        }

        const actuel = { x: evenement.clientX, y: evenement.clientY };
        this.pointeurs.set(evenement.pointerId, actuel);

        if (1 === this.pointeurs.size) {
            const dx = actuel.x - precedent.x;
            const dy = actuel.y - precedent.y;

            this.distanceParcourue += Math.hypot(dx, dy);
            this.tx += dx;
            this.ty += dy;
            // L'échelle appliquée, et non celle qu'on recalculerait : un
            // changement de mise en page au milieu du geste — un clavier
            // logiciel qui s'ouvre, un téléphone qu'on tourne — ferait sinon
            // sauter la carte sous le doigt.
            this.appliquer(this.echelleAppliquee ?? this.facteurCourant());

            return;
        }

        if (2 === this.pointeurs.size && this.ecartInitial > 0) {
            // Un pincement déplace forcément les doigts : le geste ne doit
            // jamais se conclure par l'ouverture d'une case.
            this.distanceParcourue = Infinity;

            const facteur = this.echelleInitiale * (this.ecartEntrePointeurs() / this.ecartInitial);
            this.zoom = facteur;
            this.approcherAutourDe(this.milieuDesPointeurs(), facteur);
        }
    }

    surPointerUp(evenement) {
        this.pointeurs.delete(evenement.pointerId);

        if (this.pointeurs.size < 2) {
            this.ecartInitial = null;
        }

        this.animer(true);
    }

    surClic(evenement) {
        if (this.distanceParcourue <= this.constructor.SEUIL_DE_GLISSEMENT) {
            return;
        }

        // Le doigt a glissé : c'était un déplacement de carte, pas le choix
        // d'une case.
        evenement.preventDefault();
        evenement.stopPropagation();
        this.distanceParcourue = 0;
    }

    surMolette(evenement) {
        evenement.preventDefault();

        const facteur = this.facteurCourant() * (evenement.deltaY < 0 ? 1.12 : 1 / 1.12);
        this.zoom = facteur;
        this.animer(false);
        this.approcherAutourDe({ x: evenement.clientX, y: evenement.clientY }, facteur);
    }

    // — Commandes ————————————————————————————————————————————————————————

    approcher() {
        this.zoom = this.facteurCourant() + this.pasValue;
        this.appliquer(this.zoom);
    }

    eloigner() {
        this.zoom = this.facteurCourant() - this.pasValue;
        this.appliquer(this.zoom);
    }

    /** Retour à l'ajustement : l'échelle **et** le recentrage. Un joueur perdu
     *  au bord de son désert doit pouvoir tout retrouver d'un geste. */
    revenir() {
        this.zoom = null;
        this.tx = 0;
        this.ty = 0;
        this.appliquer(this.ajustement());
    }

    /** Au redimensionnement, on ne recalcule que si le joueur n'a rien choisi :
     *  écraser son zoom parce qu'il a tourné son téléphone serait le lui
     *  reprendre. */
    ajuster() {
        this.appliquer(this.facteurCourant());
    }

    // — Calculs ——————————————————————————————————————————————————————————

    /** Le facteur qui fait entrer la grille entière, jamais au-delà de 1 :
     *  agrandir une image de tuile la rendrait floue. */
    ajustement() {
        const cadre = this.hasCadreTarget ? this.cadreTarget : this.element;
        const marges = window.getComputedStyle(cadre);
        // La boîte **de contenu**, marge intérieure déduite : getBoundingClientRect
        // l'inclut, et la carte « ajustée » débordait donc d'autant.
        const dispoLargeur = cadre.clientWidth
            - parseFloat(marges.paddingLeft) - parseFloat(marges.paddingRight);
        const dispoHauteur = cadre.clientHeight
            - parseFloat(marges.paddingTop) - parseFloat(marges.paddingBottom);
        const largeur = this.grilleTarget.offsetWidth;
        const hauteur = this.grilleTarget.offsetHeight;

        if (0 === largeur || 0 === hauteur || dispoLargeur <= 0 || dispoHauteur <= 0) {
            return 1;
        }

        return Math.min(1, dispoLargeur / largeur, dispoHauteur / hauteur);
    }

    facteurCourant() {
        return this.zoom ?? this.ajustement();
    }

    ecartEntrePointeurs() {
        const [a, b] = [...this.pointeurs.values()];

        return Math.hypot(a.x - b.x, a.y - b.y);
    }

    milieuDesPointeurs() {
        const [a, b] = [...this.pointeurs.values()];

        return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    }

    /**
     * Change l'échelle en laissant immobile le point tenu.
     *
     * Le point sous les doigts doit rester sous les doigts : zoomer autour du
     * centre du panneau fait fuir la case qu'on regardait, et c'est le premier
     * reproche fait à ce genre de carte.
     */
    approcherAutourDe(point, facteur) {
        const ancien = this.echelleAppliquee ?? this.facteurCourant();
        const borne = this.borner(facteur);
        const cadre = this.element.getBoundingClientRect();
        const centre = { x: cadre.left + cadre.width / 2, y: cadre.top + cadre.height / 2 };
        const rapport = borne / ancien;

        this.tx = (point.x - centre.x) * (1 - rapport) + this.tx * rapport;
        this.ty = (point.y - centre.y) * (1 - rapport) + this.ty * rapport;

        this.appliquer(borne);
    }

    borner(facteur) {
        return Math.min(this.maxValue, Math.max(this.minValue, facteur));
    }

    /** Le déplacement est borné : la carte ne peut pas être poussée hors de
     *  vue, sinon l'écran devient vide et rien ne dit comment revenir. */
    bornerDeplacement(facteur) {
        const cadre = this.element.getBoundingClientRect();
        const marge = 48;
        const maxX = Math.max(0, (this.grilleTarget.offsetWidth * facteur - cadre.width) / 2) + marge;
        const maxY = Math.max(0, (this.grilleTarget.offsetHeight * facteur - cadre.height) / 2) + marge;

        this.tx = Math.min(maxX, Math.max(-maxX, this.tx));
        this.ty = Math.min(maxY, Math.max(-maxY, this.ty));
    }

    animer(actif) {
        this.grilleTarget.classList.toggle('transition-transform', actif);
    }

    appliquer(facteur) {
        const borne = this.borner(facteur);

        if (null !== this.zoom) {
            this.zoom = borne;
        }

        this.echelleAppliquee = borne;
        this.bornerDeplacement(borne);

        this.grilleTarget.style.transform =
            `translate(${Math.round(this.tx)}px, ${Math.round(this.ty)}px) scale(${borne})`;

        if (this.hasFacteurTarget) {
            this.facteurTarget.textContent = `${Math.round(borne * 100)} %`;
        }
    }
}
