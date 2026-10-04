#!/usr/bin/env python3
"""Prepare les deux plans de ville (sources-sprites/v2/ville.jpeg, ville_port.jpeg)
pour le jeu : app/assets/images/ville/{ville,ville_port}.webp.

Quatre traitements, dans cet ordre :

1. **Fond blanc -> transparent**, par remplissage depuis les bords, et seulement
   depuis des pixels *blancs* : partir de n'importe quel pixel du bord rongeait
   l'eau du fleuve, que le plan touche sur deux cotes.
2. **Zones « decor » repeintes.** Le generateur a recopie le plan guide : trois
   losanges gris et leurs etiquettes « (decor) » sont restes sur le sol. Les lots
   les recouvrent, mais pas leur pourtour. On les remplace par la moyenne
   ponderee de ce qui les entoure.
3. **Fleuve en fondu** sur les deux bords du plan qu'il touche (droite et bas) : il
   y etait coupe net, contre le fond de la page.
4. Export WebP.

Usage : python3 outils/preparer-plans-de-ville.py   (Pillow seul).
"""
import os
from PIL import Image, ImageChops, ImageDraw, ImageFilter

RACINE = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SOURCES = os.path.join(RACINE, 'sources-sprites', 'v2')
SORTIE = os.path.join(RACINE, 'app/assets/images/ville')
MARQUE = (255, 0, 255)

# Les trois losanges « decor » du plan (1376 x 768) : centre, demi-largeur, demi-hauteur,
# mesures sur l'image, avec une marge pour le trait qui les borde.
LOSANGES_DECOR = [(172, 378, 76, 40), (990, 451, 76, 40), (915, 489, 74, 40)]
FONDU_DU_FLEUVE = 110  # pixels


def transparent(im: Image.Image) -> Image.Image:
    w, h = im.size
    m = im.copy()
    amorces = [(x, 0) for x in range(0, w, 6)] + [(x, h - 1) for x in range(0, w, 6)] \
        + [(0, y) for y in range(0, h, 6)] + [(w - 1, y) for y in range(0, h, 6)]
    for xy in amorces:
        if min(im.getpixel(xy)) >= 238 and m.getpixel(xy) != MARQUE:
            ImageDraw.floodfill(m, xy, MARQUE, thresh=10)
    fond = ImageChops.difference(m, Image.new('RGB', (w, h), MARQUE)).convert('L').point(lambda v: 255 if v > 0 else 0)
    alpha = fond.filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(0.8))
    sortie = im.convert('RGBA')
    sortie.putalpha(alpha)
    return sortie


def repeindre_decor(im: Image.Image) -> Image.Image:
    """Remplace les losanges gris et leurs etiquettes par la couleur ambiante.

    Le masque est un polygone explicite par losange, pas un seuil de couleur : le
    gris du generateur varie, et un seuil laissait des morceaux de lettres. Le
    remplissage est une moyenne ponderee des pixels voisins, a portee croissante
    — le centre d'un losange est loin de tout pixel valide."""
    rgb = im.convert('RGB')
    w, h = rgb.size
    masque = Image.new('L', (w, h), 0)
    d = ImageDraw.Draw(masque)
    for (cx, cy, hw, hh) in LOSANGES_DECOR:
        d.polygon([(cx, cy - hh), (cx + hw, cy), (cx, cy + hh), (cx - hw, cy)], fill=255)

    valide = ImageChops.invert(masque)
    pondere = Image.composite(rgb, Image.new('RGB', (w, h), (0, 0, 0)), valide)
    sortie = rgb.copy()
    sp, mm = sortie.load(), masque.load()
    rempli = Image.new('L', (w, h), 0)
    rp = rempli.load()
    for rayon in (10, 26, 60):
        fc = pondere.filter(ImageFilter.GaussianBlur(rayon)).load()
        fv = valide.filter(ImageFilter.GaussianBlur(rayon)).load()
        for y in range(h):
            for x in range(w):
                if mm[x, y] and not rp[x, y] and fv[x, y] > 12:
                    k = 255 / fv[x, y]
                    c = fc[x, y]
                    sp[x, y] = (min(255, int(c[0] * k)), min(255, int(c[1] * k)), min(255, int(c[2] * k)))
                    rp[x, y] = 255
    # Les bords du repeint sont adoucis : on fond avec l'original sur une frange.
    frange = masque.filter(ImageFilter.GaussianBlur(3))
    return Image.composite(sortie, rgb, frange).convert('RGBA')


def fondre_le_fleuve(im: Image.Image) -> Image.Image:
    """Estompe l'eau (bleue) vers les bords droit et bas, seulement elle."""
    w, h = im.size
    px = im.load()
    eau = Image.new('L', (w, h), 0)
    ep = eau.load()
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a > 100 and b > r + 14 and b > 90:
                ep[x, y] = 255
    eau = eau.filter(ImageFilter.GaussianBlur(3))
    ep = eau.load()
    alpha = im.getchannel('A')
    ap = alpha.load()
    for y in range(h):
        for x in range(w):
            if ep[x, y] < 8:
                continue
            d = min(w - 1 - x, h - 1 - y)  # distance au bord droit ou bas le plus proche
            if d >= FONDU_DU_FLEUVE:
                continue
            t = (d / FONDU_DU_FLEUVE) ** 1.4
            poids = ep[x, y] / 255
            ap[x, y] = int(ap[x, y] * (1 - poids * (1 - t)))
    sortie = im.copy()
    sortie.putalpha(alpha)
    return sortie


def main() -> None:
    for nom, fleuve in (('ville', False), ('ville_port', True)):
        src = os.path.join(SOURCES, nom + '.jpeg')
        im = Image.open(src).convert('RGB')
        im = repeindre_decor(im).convert('RGB')
        im = transparent(im)
        if fleuve:
            im = fondre_le_fleuve(im)
        im.save(os.path.join(SORTIE, nom + '.webp'), quality=86, method=6)
        print(nom, im.size)


if __name__ == '__main__':
    main()
