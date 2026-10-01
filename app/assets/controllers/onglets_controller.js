import { Controller } from '@hotwired/stimulus';

/*
 * Les onglets de l'écran de ville.
 *
 * La ville portait neuf sections empilées, soit plusieurs écrans de haut : pour
 * comparer sa masse salariale et son étal, il fallait faire défiler et retenir.
 * Les sections deviennent des onglets, et l'écran cesse d'être un document.
 *
 * **Tous les panneaux restent dans le document**, seulement masqués : la page
 * est rendue d'un bloc par le serveur, changer d'onglet ne demande donc aucun
 * aller-retour, et le contenu reste lisible par une recherche de page comme par
 * un lecteur d'écran qui parcourt le document.
 *
 * **Onglets et panneaux s'apparient par rang**, pas par identifiant : un
 * panneau ajouté ailleurs que dans l'ordre de son onglet décale tout ce qui
 * suit, et l'on ouvre alors le voisin. Défaut réel, attrapé par le test qui
 * compare les deux listes dans l'ordre — c'est pour cela qu'il les compare
 * dans l'ordre.
 */
export default class extends Controller {
    static targets = ['onglet', 'panneau'];
    static classes = ['actif', 'inactif'];
    /**
     * Une clé de mémoire facultative : quand elle est posée, l'onglet ouvert
     * survit au rechargement de la page. Les sections de la Résidence en
     * dépendent — régler un salaire recharge l'écran, et l'on ne doit pas
     * retomber sur la première section à chaque geste.
     */
    static values = { memoire: String };

    connect() {
        const demande = this.ongletTargets.findIndex((o) => o.dataset.ongletActif === 'true');
        const retenu = this.rangRetenu();

        this.montrer(retenu ?? (demande < 0 ? 0 : demande));
    }

    rangRetenu() {
        if (!this.hasMemoireValue || '' === this.memoireValue) {
            return null;
        }

        try {
            const rang = Number.parseInt(sessionStorage.getItem(`onglets:${this.memoireValue}`) ?? '', 10);

            return Number.isInteger(rang) && rang >= 0 && rang < this.ongletTargets.length ? rang : null;
        } catch {
            // Stockage refusé (navigation privée, politique du navigateur) :
            // l'écran marche sans, il oublie seulement la section ouverte.
            return null;
        }
    }

    retenir(rang) {
        if (!this.hasMemoireValue || '' === this.memoireValue) {
            return;
        }

        try {
            sessionStorage.setItem(`onglets:${this.memoireValue}`, String(rang));
        } catch {
            // Voir `rangRetenu()`.
        }
    }

    choisir(evenement) {
        this.montrer(this.ongletTargets.indexOf(evenement.currentTarget));
    }

    /** Flèches gauche/droite entre onglets : c'est ce qu'un lecteur d'écran
     *  annonce et ce qu'un clavier attend d'une barre d'onglets. */
    naviguer(evenement) {
        const touches = { ArrowRight: 1, ArrowLeft: -1 };
        const pas = touches[evenement.key];

        if (pas === undefined) {
            return;
        }

        evenement.preventDefault();
        const total = this.ongletTargets.length;
        const suivant = (this.ongletTargets.indexOf(evenement.currentTarget) + pas + total) % total;

        this.montrer(suivant);
        this.ongletTargets[suivant].focus();
    }

    montrer(rang) {
        const choisi = rang < 0 ? 0 : rang;

        this.ongletTargets.forEach((onglet, index) => {
            const actif = index === choisi;
            onglet.setAttribute('aria-selected', actif ? 'true' : 'false');
            onglet.setAttribute('tabindex', actif ? '0' : '-1');
            // `actifClasses` au pluriel, et une bascule par classe :
            // `this.actifClass` ne rend que **la première** des classes
            // déclarées, et classList.toggle refuse une chaîne qui contient une
            // espace. La couleur du texte de l'onglet actif n'était donc jamais
            // posée — seule sa soulignure l'était, sans la moindre erreur.
            this.actifClasses.forEach((classe) => onglet.classList.toggle(classe, actif));
            this.inactifClasses.forEach((classe) => onglet.classList.toggle(classe, !actif));
        });

        this.panneauTargets.forEach((panneau, index) => {
            panneau.hidden = index !== choisi;
        });

        this.retenir(choisi);
        this.amenerDansLaBande(this.ongletTargets[choisi]);
    }

    /**
     * Ramène l'onglet choisi dans la bande visible.
     *
     * Sur un écran étroit, la barre d'onglets tient sur une seule ligne qui
     * défile latéralement. Sans ce rappel, l'onglet ouvert restait hors champ :
     * on lisait le panneau du Port en voyant la Résidence soulignée, et rien ne
     * disait où l'on était. Tant que les onglets se repliaient sur plusieurs
     * rangées, la question ne se posait pas.
     *
     * `block: 'nearest'` n'est pas décoratif : sans lui, le navigateur fait
     * aussi défiler verticalement l'ancêtre défilant, et l'écran de ville
     * sautait à chaque changement d'onglet.
     */
    amenerDansLaBande(onglet) {
        onglet?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }
}
