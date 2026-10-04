#!/usr/bin/env python3
"""Decoupe les planches de batiments (sources-sprites/v2/*.jpeg|png, 2x2 paliers,
fond blanc) en sprites WebP a fond transparent, et ecrit leurs ancrages.

Chaque planche porte le MEME lot (plate-forme de terre battue a muret bas) dans
ses quatre cellules : le lot est l'echelle. On en tire donc :

- des sprites de **meme cadre** pour les quatre paliers d'un batiment (le cadre
  est l'union des quatre boites), si bien que le lot reste au meme endroit d'un
  palier a l'autre ;
- l'**ancrage** : centre du lot dans ce cadre (coin gauche du losange pour la
  hauteur, coin haut pour l'abscisse) et largeur du lot — `Game\\AncragesDesSprites`,
  genere, que la vue de la ville lit pour poser chaque sprite a l'echelle de son
  enclos.

Usage : python3 outils/decouper-batiments.py   (Pillow ; les planches sont dans
sources-sprites/v2/, ignore par git).
"""
import json
import os
from PIL import Image, ImageChops, ImageDraw, ImageFilter

RACINE = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SOURCES = os.path.join(RACINE, 'sources-sprites', 'v2')
SORTIE = os.path.join(RACINE, 'app/assets/images/ville/batiments')
SORTIE_LOTS = os.path.join(RACINE, 'app/assets/images/ville/lots')
PHP = os.path.join(RACINE, 'app/src/Game/AncragesDesSprites.php')

PLANCHES = {
    'atelier': 'atelier', 'auberge': 'auberge', 'caserne': 'caserne', 'entrepot': 'entrepot',
    'forge': 'forge', 'grenier': 'grenier', 'quartiers': 'quartier_habitation', 'marche': 'marche',
    'port': 'port', 'residence': 'residence_familiale', 'scribe': 'maison_des_scribes', 'temple': 'temple',
}
INSET = 6
TOLERANCE = 30
MARQUE = (255, 0, 255)


def ouvrir(nom: str) -> Image.Image:
    for ext in ('jpeg', 'jpg', 'png'):
        chemin = os.path.join(SOURCES, f'{nom}.{ext}')
        if os.path.exists(chemin):
            return Image.open(chemin).convert('RGB')
    raise FileNotFoundError(nom)


def detourer(cellule: Image.Image, sans_eau: bool = False) -> Image.Image:
    """Le fond est un blanc uni : on le fait partir par remplissage depuis les
    bords, ce qui laisse intacts les clairs enfermes dans un batiment."""
    w, h = cellule.size
    marque = cellule.copy()
    pas = 10
    amorces = [(x, 0) for x in range(0, w, pas)] + [(x, h - 1) for x in range(0, w, pas)] \
        + [(0, y) for y in range(0, h, pas)] + [(w - 1, y) for y in range(0, h, pas)]
    for xy in amorces:
        if marque.getpixel(xy) != MARQUE:
            ImageDraw.floodfill(marque, xy, MARQUE, thresh=TOLERANCE)
    fond = ImageChops.difference(marque, Image.new('RGB', (w, h), MARQUE)).convert('L').point(lambda v: 255 if v > 0 else 0)

    if sans_eau:
        # Le Port porte sa propre eau ; le plan a son fleuve : on ne garde pas
        # l'eau du sprite (bleu clair), seulement le quai, la barque, le lot.
        px = cellule.load()
        masque = fond.load()
        for y in range(h):
            for x in range(w):
                r, g, b = px[x, y]
                if b > r + 28 and b >= g - 10 and masque[x, y]:
                    masque[x, y] = 0

    alpha = fond.filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(0.7))
    sortie = cellule.convert('RGBA')
    sortie.putalpha(alpha)
    return sortie


def boite(im: Image.Image):
    return im.getchannel('A').point(lambda v: 255 if v > 40 else 0).getbbox()


def ancrage(im: Image.Image) -> dict:
    """Largeur et centre du lot, mesures sur le palier 1 (le plus nu).

    Le coin gauche du losange est sur sa ligne mediane (hauteur du centre), le
    coin haut sur son axe vertical (abscisse du centre) : on les prend plutot que
    les coins droit et bas, que cailloux, quai et barques debordent volontiers."""
    alpha = im.getchannel('A').point(lambda v: 255 if v > 90 else 0)
    gauche, haut, droite, bas = alpha.getbbox()
    w, h = alpha.size
    px = alpha.load()

    # Coin gauche : barycentre vertical des pixels de la colonne extreme.
    ys = [y for y in range(h) if any(px[x, y] for x in range(gauche, min(gauche + 4, w)))]
    cy = sum(ys) / len(ys)
    # Coin haut : barycentre horizontal des pixels de la rangee extreme.
    xs = [x for x in range(w) if any(px[x, y] for y in range(haut, min(haut + 4, h)))]
    cx = sum(xs) / len(xs)

    demi = cx - gauche
    return {'centreX': round(cx, 1), 'centreY': round(cy, 1), 'largeurDuLot': round(2 * demi, 1)}


def decouper_planche(nom: str, type_: str) -> dict:
    planche = ouvrir(nom)
    w, h = planche.size
    cellules = []
    for col, ligne in [(0, 0), (1, 0), (0, 1), (1, 1)]:
        quart = planche.crop((col * w // 2 + INSET, ligne * h // 2 + INSET, (col + 1) * w // 2 - INSET, (ligne + 1) * h // 2 - INSET))
        cellules.append(detourer(quart, sans_eau=(type_ == 'port')))

    boites = [boite(c) for c in cellules]
    g = min(b[0] for b in boites)
    t = min(b[1] for b in boites)
    d = max(b[2] for b in boites)
    b_ = max(b[3] for b in boites)
    cadre = (g, t, d, b_)

    os.makedirs(SORTIE, exist_ok=True)
    recadrees = [c.crop(cadre) for c in cellules]
    for palier, im in enumerate(recadrees, start=1):
        im.save(os.path.join(SORTIE, f'{type_}_{palier}.webp'), quality=88, method=6)

    donnees = ancrage(recadrees[0])
    donnees.update({'largeur': recadrees[0].width, 'hauteur': recadrees[0].height})
    return donnees


def decouper_lots() -> dict:
    planche = ouvrir('c')
    w, h = planche.size
    os.makedirs(SORTIE_LOTS, exist_ok=True)
    sortie = {}
    for i, classe in enumerate(['s', 'm', 'l']):
        tiers = planche.crop((i * w // 3 + INSET, INSET, (i + 1) * w // 3 - INSET, h - INSET))
        im = detourer(tiers)
        im = im.crop(boite(im))
        im.save(os.path.join(SORTIE_LOTS, f'lot_{classe}.webp'), quality=88, method=6)
        donnees = ancrage(im)
        donnees.update({'largeur': im.width, 'hauteur': im.height})
        sortie[classe] = donnees
    return sortie


def php(batiments: dict, lots: dict) -> str:
    def tableau(d: dict, indent: str) -> str:
        lignes = [f"{indent}'{cle}' => ['largeur' => {v['largeur']}, 'hauteur' => {v['hauteur']}, 'centreX' => {v['centreX']}, "
                  f"'centreY' => {v['centreY']}, 'largeurDuLot' => {v['largeurDuLot']}]," for cle, v in d.items()]
        return '\n'.join(lignes)

    return f"""<?php

declare(strict_types=1);

namespace App\\Game;

/**
 * Ou se trouve le lot dans chaque sprite, et quelle largeur il y fait.
 *
 * **GENERE** par `outils/decouper-batiments.py` — ne pas editer a la main. Les
 * valeurs sont en pixels du sprite : `largeur` et `hauteur` sont celles du cadre
 * (communes aux quatre paliers d'un batiment), `centreX` et `centreY` celles du
 * centre du losange du lot, `largeurDuLot` sa largeur. La vue de la ville pose
 * le sprite pour que ce centre tombe sur le centre de l'enclos, a l'echelle qui
 * donne au lot la largeur de l'enclos.
 */
final class AncragesDesSprites
{{
    /**
     * @var array<string, array{{largeur: int, hauteur: int, centreX: float, centreY: float, largeurDuLot: float}}>
     */
    public const array BATIMENTS = [
{tableau(batiments, '        ')}
    ];

    /**
     * Les lots vides, par classe : `s`, `m`, `l`.
     *
     * @var array<string, array{{largeur: int, hauteur: int, centreX: float, centreY: float, largeurDuLot: float}}>
     */
    public const array LOTS = [
{tableau(lots, '        ')}
    ];
}}
"""


def main() -> None:
    batiments = {}
    for nom, type_ in PLANCHES.items():
        batiments[type_] = decouper_planche(nom, type_)
        print(type_, batiments[type_])
    lots = decouper_lots()
    print('lots', lots)
    with open(PHP, 'w') as f:
        f.write(php(batiments, lots))
    json.dump({'batiments': batiments, 'lots': lots}, open('/tmp/ancrages.json', 'w'))


if __name__ == '__main__':
    main()
