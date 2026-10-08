#!/usr/bin/env python3
"""Genere des FAUX icones — des substituts — pour les ressources qui n'ont pas encore
leur planche : un emoji pose sur la meme plaque de bois que les vraies illustrations.

    grauwacke, poisson, dattes, outils, armes  -> app/assets/images/ressources/<valeur>.webp
    deben                                      -> copie du pictogramme `interface/deben.webp`

**Ce sont des placeholders**, a remplacer par de vraies illustrations : generer la planche
manquante, l'ajouter a `outils/decouper-icones.py` (le nom du fichier est la valeur de
l'enumeration `Ressource`), puis retirer l'entree d'ici — ce script n'ecrase jamais un
fichier qu'il n'a pas lui-meme liste. Il a besoin de la police emoji de macOS ; sans elle
il s'arrete sans rien ecrire.

Usage : python3 outils/faux-icones.py   (Pillow seul).
"""
import os
import shutil
import sys
from PIL import Image, ImageDraw, ImageFont

RACINE = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
RESSOURCES = os.path.join(RACINE, 'app/assets/images/ressources')
INTERFACE = os.path.join(RACINE, 'app/assets/images/interface')
POLICE_EMOJI = '/System/Library/Fonts/Apple Color Emoji.ttc'

FAUX = {
    'grauwacke': '🪨',
    'poisson': '🐟',
    'dattes': '🌴',
    'outils': '⛏️',
    'armes': '🗡️',
}
LARGEUR, HAUTEUR = 224, 176


def plaque() -> Image.Image:
    """La plaque de bois, en perspective isometrique, comme sous les vraies icones."""
    im = Image.new('RGBA', (LARGEUR, HAUTEUR), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, haut, bas = LARGEUR // 2, 96, 160
    # Tranche (deux faces sombres) puis dessus clair.
    gauche = [(10, haut + 24), (cx, bas), (cx, bas + 12), (10, haut + 36)]
    droite = [(LARGEUR - 10, haut + 24), (cx, bas), (cx, bas + 12), (LARGEUR - 10, haut + 36)]
    d.polygon(gauche, fill=(176, 108, 44, 255))
    d.polygon(droite, fill=(150, 90, 34, 255))
    dessus = [(cx, haut - 14), (LARGEUR - 10, haut + 24), (cx, bas), (10, haut + 24)]
    d.polygon(dessus, fill=(228, 160, 80, 255), outline=(140, 82, 30, 255))
    return im


def emoji(symbole: str) -> Image.Image:
    police = ImageFont.truetype(POLICE_EMOJI, 160)
    im = Image.new('RGBA', (200, 200), (0, 0, 0, 0))
    ImageDraw.Draw(im).text((20, 20), symbole, font=police, embedded_color=True)
    return im.crop(im.getbbox())


def main() -> None:
    if not os.path.isfile(POLICE_EMOJI):
        sys.exit('Police emoji de macOS introuvable : rien ecrit.')
    for nom, symbole in FAUX.items():
        im = plaque()
        e = emoji(symbole)
        e.thumbnail((104, 104), Image.LANCZOS)
        im.alpha_composite(e, ((LARGEUR - e.width) // 2, 92 - e.height + 30))
        im.save(os.path.join(RESSOURCES, nom + '.webp'), quality=92, method=6)
        print('faux icone', nom)
    shutil.copyfile(os.path.join(INTERFACE, 'deben.webp'), os.path.join(RESSOURCES, 'deben.webp'))
    print('deben (copie du pictogramme)')


if __name__ == '__main__':
    main()
