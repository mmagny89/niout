import { Controller } from '@hotwired/stimulus';

/*
 * Les compteurs de la barre de jeu disent ce qui vient de bouger.
 *
 * La barre se recharge (cadre Turbo) après chaque action de la fenêtre ou chaque
 * quinzaine : on mémorise les valeurs affichées, et au rechargement suivant on
 * compare. Ce qui a monté s'éclaire, ce qui a baissé rougit, l'écart flotte un
 * instant sous le chiffre. Pure décoration — le chiffre est dans le texte, l'écart
 * est `aria-hidden` — donc `sessionStorage` indisponible ne casse rien.
 *
 * Chaque compteur porte `data-compteurs-target="compteur"`, `data-nom` (stable
 * d'un rendu à l'autre) et `data-valeur` (un entier).
 */
export default class extends Controller {
    static targets = ['compteur'];
    static values = { cle: String };

    connect() {
        const avant = this.lire();
        const maintenant = {};

        for (const element of this.compteurTargets) {
            const nom = element.dataset.nom;
            const valeur = Number.parseInt(element.dataset.valeur ?? '', 10);
            if (!nom || Number.isNaN(valeur)) continue;

            maintenant[nom] = valeur;
            if (avant && nom in avant && avant[nom] !== valeur) {
                this.signaler(element, valeur - avant[nom]);
            }
        }

        this.ecrire(maintenant);
    }

    signaler(element, ecart) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        element.classList.add(ecart > 0 ? 'compteur--hausse' : 'compteur--baisse');

        const bulle = document.createElement('span');
        bulle.className = `compteur__ecart compteur__ecart--${ecart > 0 ? 'hausse' : 'baisse'}`;
        bulle.setAttribute('aria-hidden', 'true');
        bulle.textContent = `${ecart > 0 ? '+' : '−'}${Math.abs(ecart)}`;
        element.append(bulle);
        bulle.addEventListener('animationend', () => bulle.remove(), { once: true });
    }

    lire() {
        try {
            return JSON.parse(sessionStorage.getItem(`compteurs:${this.cleValue}`) ?? 'null');
        } catch {
            return null;
        }
    }

    ecrire(valeurs) {
        try {
            sessionStorage.setItem(`compteurs:${this.cleValue}`, JSON.stringify(valeurs));
        } catch {
            // Navigation privée ou stockage plein : l'effet disparaît, rien d'autre.
        }
    }
}
