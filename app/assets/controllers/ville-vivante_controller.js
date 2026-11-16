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
 * **Les conversations.** Le serveur a écrit chaque réplique et dit où se tient chacun ; ce
 * contrôleur ne fait que **montrer des bulles**. Elles se montrent quand la lecture est arrêtée sur
 * un moment (au curseur, ou en pause), toutes ensemble ; et, si l'on a coché « Lire les
 * conversations », la lecture **s'arrête d'elle-même** le temps de lire chaque réplique — à
 * 55 ms par caractère, entre 1,8 s et 4,5 s — puis repart. Lire à 4 s par jour est impossible, et
 * cela vaut mieux qu'une bulle qui clignote.
 *
 * **Après un passage de quinzaine** (`lancer`) : le film de la quinzaine qui vient de passer se joue tout
 * seul, à vitesse normale, quand on était sur la ville vue d'en haut — jamais pour qui demande moins de
 * mouvement, ni si l'onglet est caché.
 *
 * **Politesse.** La lecture se met en pause quand l'onglet n'est plus visible, et quand
 * on clique une silhouette pour lire sa fiche. Le suivi met une silhouette en valeur
 * et atténue les autres : il ne déplace rien.
 */
const MS_PAR_JOUR = 4000;
// Un jour compte quatre moments ; à la mi-moment, chacun est arrivé (`FilmDeLaVille::MARCHE` et `AVANCE`).
const MS_PAR_MOMENT = MS_PAR_JOUR / 4;

// Le moment de la journée sur la ville : la teinte (fondue par `multiply` : le blanc ne change rien) et la couleur de
// la pastille. Aube, matin, après-midi, soir — dans l'ordre de `MomentDeLaJournee`.
const TEINTES = ['rgb(255 226 210)', 'rgb(255 255 255)', 'rgb(255 242 214)', 'rgb(170 178 222)'];
const PASTILLES = ['#f4a261', '#f6d365', '#f0a030', '#4b5fa8'];

export default class extends Controller {
    static targets = ['film', 'statique', 'barre', 'lecture', 'curseur', 'jour', 'suivi', 'maintenant', 'figure', 'bulle', 'ecouter', 'ciel', 'moment', 'momentPastille', 'momentTexte'];
    static values = { film: Object, libelles: Object, lancer: Boolean };

    connect() {
        this.animations = null;
        this.enFilm = false;
        this.enLecture = false;
        this.vitesseCourante = 1;
        this.image = null;
        this.enConversation = false;
        this.jeton = 0;
        this.prochaine = 0;

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

        // Revenu d'un passage de quinzaine : on joue le film qui vient de s'écrire, à vitesse normale.
        if (this.lancerValue && this.filmValue.jours > 0 && !document.hidden) {
            this.oublierLaDemande();
            requestAnimationFrame(() => {
                if (this.element.isConnected && !this.enLecture) {
                    this.basculer();
                }
            });
        }
    }

    /**
     * L'adresse ne garde pas `film=1` : recharger la page, ou y revenir, ne doit pas relancer le film.
     */
    oublierLaDemande() {
        try {
            const adresse = new URL(window.location.href);

            if (adresse.searchParams.has('film')) {
                adresse.searchParams.delete('film');
                window.history.replaceState(window.history.state, '', adresse);
            }
        } catch (erreur) {
            // Une adresse qu'on ne sait pas lire n'empêche pas de jouer.
        }
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

        this.annulerLaConversation();
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

        // Le curseur compte des jours. On se pose sur la première conversation du jour s'il y en
        // a une — c'est là qu'il y a quelque chose à lire —, sinon sur l'aube.
        const debutDuJour = (Number(this.curseurTarget.value) - 1) * 4;
        const parole = (this.filmValue.paroles || []).find((p) => p.t >= debutDuJour && p.t < debutDuJour + 4);
        this.placer((parole ? parole.t + 0.5 : debutDuJour) * MS_PAR_MOMENT);
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
        this.cacherLesBulles();
        this.filmTarget.hidden = true;

        if (this.hasStatiqueTarget) {
            this.statiqueTarget.hidden = false;
        }

        this.maintenantTarget.hidden = true;
        this.enFilm = false;

        if (this.hasCielTarget) {
            this.cielTarget.hidden = true;
            this.momentTarget.hidden = true;
        }

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

        this.prochaine = this.premiereParoleApres(this.image.currentTime / MS_PAR_MOMENT);
        this.cacherLesBulles();
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
        this.prochaine = this.premiereParoleApres(milliseconde / MS_PAR_MOMENT);
    }

    // ---- L'affichage de la barre -----------------------------------------------------

    demarrerLaBoucle() {
        this.arreterLaBoucle();
        const tourner = () => {
            this.afficher();
            this.verifierLesParoles();
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
        this.jourTarget.textContent = `Jour ${jour} sur ${film.jours} · ${film.libellesDesMoments[moment]}`;
        this.teinterLeCiel(moment);

        // Arrêté sur un moment, on voit ce qui s'y dit — toutes les bulles, ensemble.
        if (!this.enLecture && !this.enConversation) {
            this.montrerLesBullesDuMoment(Math.floor(this.image.currentTime / MS_PAR_MOMENT));
        }

        const termine = !this.enLecture && this.image.currentTime >= film.jours * MS_PAR_JOUR - 1;
        this.lectureTarget.textContent = this.enLecture
            ? this.libellesValue.pause
            : (termine ? this.libellesValue.lecture : (this.enFilm ? this.libellesValue.reprendre : this.libellesValue.lecture));
    }

    /**
     * Le moment de la journée, sur la ville — seulement quand le film est à l'écran.
     */
    teinterLeCiel(moment) {
        if (!this.hasCielTarget) {
            return;
        }

        const visible = this.enFilm;
        this.cielTarget.hidden = !visible;
        this.momentTarget.hidden = !visible;

        if (visible) {
            this.cielTarget.style.backgroundColor = TEINTES[moment];
            this.momentPastilleTarget.style.backgroundColor = PASTILLES[moment];
            this.momentTexteTarget.textContent = this.filmValue.libellesDesMoments[moment];
        }
    }

    // ---- Les conversations -----------------------------------------------------------

    /**
     * Les bulles d'une conversation, dans l'ordre de celui qui parle puis de celui qui répond.
     */
    bullesDe(rang) {
        const bulles = this.bulleTargets.filter((bulle) => Number(bulle.dataset.parole) === rang);

        return [bulles.find((b) => 'a' === b.dataset.cote), bulles.find((b) => 'b' === b.dataset.cote)];
    }

    premiereParoleApres(moment) {
        const paroles = this.filmValue.paroles || [];
        const rang = paroles.findIndex((parole) => parole.t + 0.5 > moment);

        return -1 === rang ? paroles.length : rang;
    }

    montrerLesBullesDuMoment(moment) {
        this.bulleTargets.forEach((bulle) => {
            const parole = (this.filmValue.paroles || [])[Number(bulle.dataset.parole)];
            bulle.style.opacity = parole && Math.floor(parole.t) === moment ? '1' : '0';
        });
    }

    cacherLesBulles() {
        this.bulleTargets.forEach((bulle) => {
            bulle.style.opacity = '0';
        });
    }

    /**
     * Pendant la lecture, si l'on a demandé à lire les conversations : au moment où une
     * conversation commence — ceux qui parlent ont fini de marcher —, on s'arrête.
     */
    verifierLesParoles() {
        const paroles = this.filmValue.paroles || [];

        if (!this.enLecture || this.enConversation || !this.hasEcouterTarget) {
            return;
        }

        const moment = this.image.currentTime / MS_PAR_MOMENT;

        while (this.prochaine < paroles.length && paroles[this.prochaine].t + 0.5 <= moment) {
            const parole = paroles[this.prochaine];
            const rang = this.prochaine;
            ++this.prochaine;

            // Une conversation déjà passée — on a sauté, ou accéléré — ne se lit pas après coup.
            if (this.ecouterTarget.checked && moment < parole.t + 1) {
                this.lire(rang, parole);

                return;
            }
        }
    }

    async lire(rang, parole) {
        const jeton = ++this.jeton;
        const [bulleA, bulleB] = this.bullesDe(rang);
        const duree = (texte) => Math.min(4500, Math.max(1800, texte.length * 55));
        const attendre = (ms) => new Promise((resoudre) => setTimeout(resoudre, ms));

        this.enConversation = true;
        this.animations.forEach((animation) => animation.pause());
        this.arreterLaBoucle();

        if (bulleA) {
            bulleA.style.opacity = '1';
            await attendre(duree(parole.texteA));
            bulleA.style.opacity = '0';
        }

        if (jeton !== this.jeton) {
            return;
        }

        if (bulleB) {
            bulleB.style.opacity = '1';
            await attendre(duree(parole.texteB));
            bulleB.style.opacity = '0';
        }

        // Annulée entre-temps (pause, curseur, retour) : on ne relance rien.
        if (jeton !== this.jeton) {
            return;
        }

        this.enConversation = false;

        if (this.enLecture) {
            this.animations.forEach((animation) => animation.play());
            this.demarrerLaBoucle();
        }
    }

    annulerLaConversation() {
        ++this.jeton;
        this.enConversation = false;
        this.cacherLesBulles();
    }
}
