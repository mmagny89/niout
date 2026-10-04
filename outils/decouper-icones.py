#!/usr/bin/env python3
"""Decoupe les planches d'icones et de portraits (sources-sprites/) en images
individuelles, nommees d'apres l'enumeration du jeu :

- ressources.jpeg (4x4)  -> app/assets/images/ressources/<ressource>.webp
- luxe.jpeg       (3x2)  -> app/assets/images/ressources/<ressource>.webp
- craft.jpeg      (5x3)  -> app/assets/images/ressources/<ressource>.webp
- dieux.jpeg      (4x2)  -> app/assets/images/dieux/<divinite>.webp

Les ressources sont des objets poses sur une plaque, sur fond uni : fond retire par
remplissage depuis les bords, **meme boite de decoupe pour toute une planche** — la
plaque a la meme taille d'une icone a l'autre, comme l'echelle d'un lot dans la ville.
Les portraits gardent leur fond dore et leur halo (c'est leur cadre) : simple decoupe.

Usage : python3 outils/decouper-icones.py   (Pillow seul).
"""
import os
from PIL import Image, ImageChops, ImageDraw, ImageFilter

RACINE = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SOURCES = os.path.join(RACINE, 'sources-sprites')
RESSOURCES = os.path.join(RACINE, 'app/assets/images/ressources')
DIEUX = os.path.join(RACINE, 'app/assets/images/dieux')
MARQUE = (255, 0, 255)
LARGEUR_MAX = 256

# (planche, colonnes, lignes, {(colonne, ligne): valeur de Ressource}, tolerance du fond)
PLANCHES = [
    ('ressources.jpeg', 4, 4, {
        (0, 0): 'argile', (1, 0): 'roseaux', (2, 0): 'bois_local', (3, 0): 'calcaire',
        (0, 1): 'gres', (1, 1): 'granite', (2, 1): 'albatre', (3, 1): 'cuivre',
        (0, 2): 'turquoise', (1, 2): 'natron', (2, 2): 'sel', (3, 2): 'encens',
        (0, 3): 'myrrhe', (1, 3): 'ble', (2, 3): 'orge', (3, 3): 'lin',
    }, 20),
    ('luxe.jpeg', 3, 2, {
        (0, 0): 'bois_de_cedre', (1, 0): 'ivoire', (2, 0): 'ebene',
        (0, 1): 'or', (1, 1): 'lapis_lazuli', (2, 1): 'peaux_et_plumes',
    }, 22),
    # Trois rangees de cinq : la troisieme reprend la deuxieme en variante. On garde
    # la deuxieme, sauf l'amulette — celle de la troisieme est « incrustee de turquoise ».
    ('craft.jpeg', 5, 3, {
        (0, 0): 'poterie', (1, 0): 'papyrus', (2, 0): 'vannerie', (3, 0): 'sandales', (4, 0): 'tissus',
        (0, 1): 'biere', (1, 1): 'pain', (3, 1): 'statuettes', (4, 1): 'vases',
        (2, 2): 'bijoux',
    }, 24),
]
DIVINITES = ['amon_re', 'hapi', 'isis', 'thot', 'osiris', 'sekhmet', 'ptah', 'sobek']


def detourer(cellule: Image.Image, tolerance: int) -> Image.Image:
    cellule = cellule.convert('RGB')
    w, h = cellule.size
    m = cellule.copy()
    pas = 6
    amorces = [(x, 0) for x in range(0, w, pas)] + [(x, h - 1) for x in range(0, w, pas)] \
        + [(0, y) for y in range(0, h, pas)] + [(w - 1, y) for y in range(0, h, pas)]
    for xy in amorces:
        if m.getpixel(xy) != MARQUE:
            ImageDraw.floodfill(m, xy, MARQUE, thresh=tolerance)
    fond = ImageChops.difference(m, Image.new('RGB', (w, h), MARQUE)).convert('L').point(lambda v: 255 if v > 0 else 0)
    alpha = fond.filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(0.7))
    sortie = cellule.convert('RGBA')
    sortie.putalpha(alpha)
    return sortie


def boite(im: Image.Image):
    return im.getchannel('A').point(lambda v: 255 if v > 40 else 0).getbbox()


def decouper_ressources() -> None:
    os.makedirs(RESSOURCES, exist_ok=True)
    for fichier, colonnes, lignes, noms, tolerance in PLANCHES:
        planche = Image.open(os.path.join(SOURCES, fichier)).convert('RGB')
        w, h = planche.size
        cw, ch = w // colonnes, h // lignes
        icones = {}
        for (col, ligne), nom in noms.items():
            cellule = planche.crop((col * cw + 4, ligne * ch + 4, (col + 1) * cw - 4, (ligne + 1) * ch - 4))
            icones[nom] = detourer(cellule, tolerance)
        boites = [boite(i) for i in icones.values()]
        cadre = (min(b[0] for b in boites), min(b[1] for b in boites), max(b[2] for b in boites), max(b[3] for b in boites))
        for nom, im in icones.items():
            im = im.crop(cadre)
            if im.width > LARGEUR_MAX:
                im = im.resize((LARGEUR_MAX, round(im.height * LARGEUR_MAX / im.width)), Image.LANCZOS)
            im.save(os.path.join(RESSOURCES, nom + '.webp'), quality=90, method=6)
        print(fichier, len(icones), 'icones, cadre', cadre)


def decouper_dieux() -> None:
    os.makedirs(DIEUX, exist_ok=True)
    planche = Image.open(os.path.join(SOURCES, 'dieux.jpeg')).convert('RGB')
    w, h = planche.size
    cw, ch = w // 4, h // 2
    for i, nom in enumerate(DIVINITES):
        col, ligne = i % 4, i // 4
        im = planche.crop((col * cw + 3, ligne * ch + 3, (col + 1) * cw - 3, (ligne + 1) * ch - 3))
        if im.width > LARGEUR_MAX:
            im = im.resize((LARGEUR_MAX, round(im.height * LARGEUR_MAX / im.width)), Image.LANCZOS)
        im.save(os.path.join(DIEUX, nom + '.webp'), quality=88, method=6)
    print('dieux', len(DIVINITES))


if __name__ == '__main__':
    decouper_ressources()
    decouper_dieux()
