#!/usr/bin/env python3
"""Decoupe les planches de batiments (sources-sprites/*.jpg, 2x2 paliers) en
sprites WebP a fond transparent : app/assets/images/ville/batiments/<type>_<palier>.webp

Le fond de chaque quart de planche est un aplat beige : on le fait partir par
remplissage depuis les bords (tolerance faible), ce qui laisse intacts les
sables clairs *enfermes* dans un batiment. Les bords sont adoucis d'un pixel.

Usage : python3 outils/decouper-batiments.py   (necessite Pillow ; les planches
sont dans sources-sprites/, ignore par git).
"""
import os
import sys
from PIL import Image, ImageDraw, ImageFilter, ImageChops

RACINE = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SOURCES = os.path.join(RACINE, 'sources-sprites')
SORTIE = os.path.join(RACINE, 'app/assets/images/ville/batiments')
PLANCHES = {
    'atelier': 'atelier', 'auberge': 'auberge', 'caserne': 'caserne', 'entrepot': 'entrepot',
    'forge': 'forge', 'grenier': 'grenier', 'habitation': 'quartier_habitation', 'marché': 'marche',
    'port': 'port', 'residence': 'residence_familiale', 'scribe': 'maison_des_scribes', 'temple': 'temple',
}
INSET = 8
TOLERANCE = int(os.environ.get('TOLERANCE', 34))
MARQUE = (255, 0, 255)


def detourer(quart: Image.Image) -> Image.Image:
    quart = quart.convert('RGB')
    w, h = quart.size
    marque = quart.copy()
    # Le fond vient des bords : on amorce le remplissage tout autour.
    pas = 12
    amorces = [(x, 0) for x in range(0, w, pas)] + [(x, h - 1) for x in range(0, w, pas)] \
        + [(0, y) for y in range(0, h, pas)] + [(w - 1, y) for y in range(0, h, pas)]
    for xy in amorces:
        if marque.getpixel(xy) != MARQUE:
            ImageDraw.floodfill(marque, xy, MARQUE, thresh=TOLERANCE)
    # Masque : opaque partout sauf ce qui a pris la couleur de marque.
    fond = ImageChops.difference(marque, Image.new('RGB', (w, h), MARQUE)).convert('L').point(lambda v: 255 if v > 0 else 0)
    # Le bord de l'aplat est durci : on le rogne d'un pixel puis on l'adoucit.
    alpha = fond.filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(0.8))
    sortie = quart.convert('RGBA')
    sortie.putalpha(alpha)
    boite = alpha.point(lambda v: 255 if v > 40 else 0).getbbox()
    return sortie.crop(boite) if boite else sortie


def main() -> None:
    os.makedirs(SORTIE, exist_ok=True)
    for source, type_ in PLANCHES.items():
        chemin = os.path.join(SOURCES, source + '.jpg')
        planche = Image.open(chemin)
        w, h = planche.size
        for palier, (col, ligne) in enumerate([(0, 0), (1, 0), (0, 1), (1, 1)], start=1):
            quart = planche.crop((col * w // 2 + INSET, ligne * h // 2 + INSET,
                                  (col + 1) * w // 2 - INSET, (ligne + 1) * h // 2 - INSET))
            detourer(quart).save(os.path.join(SORTIE, f'{type_}_{palier}.webp'), quality=88, method=6)
        print(type_, 'ok')


if __name__ == '__main__':
    main()
