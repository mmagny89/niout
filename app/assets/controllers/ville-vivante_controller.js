import { Controller } from '@hotwired/stimulus';

/*
 * Le film de la quinzaine passée, sur la ville vue d'en haut.
 *
 * **Il joue, il ne décide pas.** Le serveur a calculé, pour chaque silhouette, les
 * points par où elle passe et l'instant où elle y est (`FilmDeLaVille`) : le lieu,
 * le chemin par les rues, le geste, tout vient de lui. Ce contrôleur n'en fait que
 * des animations — l'API d'animation du navigateur interpole `left`, `top`, et
 * bascule `opacity` et `transform` d'un coup — et en tient les commandes : lecture,
 * pause, vitesse, jour, suivi. **Aucune règle du jeu ne vit ici.**
 *
 * **Rien n'est nécessaire à la page.** Sans ce contrôleur, la ville montre ses
 * silhouettes immobiles et la frise de la fenêtre avance jour par jour : la barre de
 * lecture reste cachée, et ce n'est pas un défaut. Elle l'est aussi **quand
 * l'utilisateur demande moins de mouvement** (`prefers-reduced-motion`) : on ne lance
 * rien, on n'anime rien, on ne montre aucune commande qui ne servirait pas.
 *
 * **Le temps.** Une quinzaine de 15 jours dure 60 s à la vitesse normale (4 s par
 * jour) ; « rapide » la fait en 30 s, « très rapide » en 10 s. Les points sont datés
 * en moments (quatre par jour) : un offset est `t / moments`.
 *
 * **Politesse.** La lecture se met en pause quand l'onglet n'est plus visible, et quand
 * on clique une silhouette pour lire sa fiche. Le suivi met une silhouette en valeur
 * et atténue les autres : il ne déplace rien.
 */
const MS_PAR_JOUR = 4000;

export default class extends Controller {
    static targets = ['film', 'statique', 'barre', 'lecture', 'curseur', 'jour', 'suivi', 'maintenant', 'figure'];
    static values = { film: Object, libelles: Object };

    connect() {
        this.animations = null;
        this.enFilm = false;
        this.enLecture = false;
        this.vitesseCourante = 1;
        this.image = null;

        if (!this.hasFilmValue || !Array.isArray(this.filmValue.figures) || !this.hasBarreTarget) {
            return;
        }

        // Moins de mouvement : ni animation, ni commande. La frise suffit.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.barreTarget.hidden = false;
        this.auChangementDeVisibilite = () => {
            if (document.hidden) {
                this.pause();
            }
        };
        document.addEventListener('visibilitychange', this.auChangementDeVisibilite);
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.auChangementDeVisibilite);
        this.arreterLaBoucle();
        (this.animations || []).forEach((animation) => animation.cancel());
        this.animations = null;
    }

    // ---- Les commandes ---------------------------------------------------------------

    basculer() {
        this.entrerEnFilm();

        if (this.enLecture) {
            this.pause();
        } else {
            this.jouer();
        }
    }

    pause() {
        if (!this.animations) {
            return;
        }

        this.animations.forEach((animation) => animation.pause());
        this.enLecture = false;
        this.arreterLaBoucle();
        this.afficher();
    }

    vitesse(evenement) {
        this.vitesseCourante = Number(evenement.currentTarget.dataset.vitesse);

        this.element.querySelectorAll('[data-action="ville-vivante#vitesse"]').forEach((bouton) => {
            bouton.setAttribute('aria-pressed', bouton === evenement.currentTarget ? 'true' : 'false');
        });

        (this.animations || []).forEach((animation) => {
            animation.playbackRate = this.vitesseCourante;
        });
    }

    aller() {
        this.entrerEnFilm();
        this.pause();
        this.placer((Number(this.curseurTarget.value) - 1) * MS_PAR_JOUR);
        this.afficher();
    }

    suivre() {
        const cle = this.suiviTarget.value;

        this.figureTargets.forEach((figure) => {
            const suivie = '' !== cle && figure.dataset.cle === cle;
            figure.style.filter = '' === cle || suivie ? '' : 'opacity(0.45)';
            figure.style.outline = suivie ? '2px solid rgb(28 74 107)' : '';
            figure.style.outlineOffset = suivie ? '2px' : '';
            figure.style.borderRadius = suivie ? '4px' : '';
        });
    }

    maintenant() {
        this.pause();
        this.filmTarget.hidden = true;

        if (this.hasStatiqueTarget) {
            this.statiqueTarget.hidden = false;
        }

        this.maintenantTarget.hidden = true;
        this.enFilm = false;
        this.lectureTarget.textContent = this.libellesValue.lecture;
    }

    // ---- Le film ---------------------------------------------------------------------

    entrerEnFilm() {
        if (this.enFilm) {
            return;
        }

        this.construire();
        this.filmTarget.hidden = false;

        if (this.hasStatiqueTarget) {
            this.statiqueTarget.hidden = true;
        }

        this.maintenantTarget.hidden = false;
        this.enFilm = true;
        this.placer((Number(this.curseurTarget.value) - 1) * MS_PAR_JOUR);
    }

    /**
     * Une animation par propriété qui change, par silhouette : la position (continue),
     * la présence et le sens du regard (qui basculent d'un coup, à l'instant du point),
     * et — seulement pour qui se couche — les deux dessins.
     */
    construire() {
        if (this.animations) {
            return;
        }

        const film = this.filmValue;
        const duree = film.jours * MS_PAR_JOUR;
        const sauts = 'steps(1, end)';
        this.animations = [];

        this.figureTargets.forEach((element) => {
            const points = film.figures[Number(element.dataset.index)].points;
            const decalage = (point) => point[0] / film.moments;
            const options = { duration: duree, fill: 'both', easing: 'linear' };
            const ajouter = (cible, images) => {
                const animation = cible.animate(images, options);
                animation.pause();
                this.animations.push(animation);
            };

            ajouter(element, points.map((p) => ({
                offset: decalage(p),
                left: `${p[1]}%`,
                top: `${p[2]}%`,
                // Les pieds décident de la profondeur : celui de devant cache celui de derrière.
                zIndex: String(Math.round(p[2] * 7.68 + 60)),
            })));
            ajouter(element, points.map((p) => ({ offset: decalage(p), opacity: String(p[3]), easing: sauts })));
            ajouter(element, points.map((p) => ({ offset: decalage(p), transform: p[5] ? 'scaleX(-1)' : 'scaleX(1)', easing: sauts })));

            if (points.some((p) => 1 === p[4])) {
                const debout = element.querySelector('[data-part="debout"]');
                const couchee = element.querySelector('[data-part="couchee"]');
                ajouter(debout, points.map((p) => ({ offset: decalage(p), opacity: p[4] ? '0' : '1', easing: sauts })));
                ajouter(couchee, points.map((p) => ({ offset: decalage(p), opacity: p[4] ? '1' : '0', easing: sauts })));
            }
        });

        this.image = this.animations[0];
        this.image.onfinish = () => {
            this.enLecture = false;
            this.arreterLaBoucle();
            this.afficher();
        };
    }

    jouer() {
        // Arrivé au bout, on repart du début.
        if (this.image.currentTime >= this.filmValue.jours * MS_PAR_JOUR - 1) {
            this.placer(0);
        }

        this.animations.forEach((animation) => {
            animation.playbackRate = this.vitesseCourante;
            animation.play();
        });
        this.enLecture = true;
        this.demarrerLaBoucle();
        this.afficher();
    }

    placer(milliseconde) {
        (this.animations || []).forEach((animation) => {
            animation.currentTime = milliseconde;
        });
    }

    // ---- L'affichage de la barre -----------------------------------------------------

    demarrerLaBoucle() {
        this.arreterLaBoucle();
        const tourner = () => {
            this.afficher();
            this.boucle = requestAnimationFrame(tourner);
        };
        this.boucle = requestAnimationFrame(tourner);
    }

    arreterLaBoucle() {
        if (this.boucle) {
            cancelAnimationFrame(this.boucle);
            this.boucle = null;
        }
    }

    afficher() {
        if (!this.image) {
            return;
        }

        const film = this.filmValue;
        const t = Math.min(this.image.currentTime ?? 0, film.jours * MS_PAR_JOUR - 1);
        const jour = Math.floor(t / MS_PAR_JOUR) + 1;
        const moment = Math.min(3, Math.floor(((t % MS_PAR_JOUR) / MS_PAR_JOUR) * 4));

        if (Number(this.curseurTarget.value) !== jour) {
            this.curseurTarget.value = String(jour);
        }

        this.curseurTarget.setAttribute('aria-valuetext', `Jour ${jour} sur ${film.jours}, ${film.libellesDesMoments[moment].toLowerCase()}`);
        this.jourTarget.textContent = `Jour ${jour} sur ${film.jours}`;

        const termine = !this.enLecture && this.image.currentTime >= film.jours * MS_PAR_JOUR - 1;
        this.lectureTarget.textContent = this.enLecture
            ? this.libellesValue.pause
            : (termine ? this.libellesValue.lecture : (this.enFilm ? this.libellesValue.reprendre : this.libellesValue.lecture));
    }
}
