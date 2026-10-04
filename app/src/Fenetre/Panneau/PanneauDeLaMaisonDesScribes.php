<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\AlphabetDesScribes;
use App\Game\CleDeLecture;
use App\Game\Dechiffrage;
use App\Game\Enquetes;
use App\Game\ExerciceDesSons;
use App\Game\FilRouge;
use App\Game\Inscription;
use App\Game\LeconDeNiout;
use App\Game\LectureDeCartouche;
use App\Game\MissionCatalogue;
use App\Game\PalierDErudition;
use App\Game\SigneAlphabetique;
use App\Game\SteleHistorique;
use App\Game\SymboleHieroglyphique;
use App\Game\TranscriptionDuNom;
use App\Game\TypeDeBatiment;

/**
 * Le panneau de la Maison des scribes : écriture, déchiffrage, exercices, énigmes
 * et dossiers d'enquête.
 *
 * Le plus lourd des panneaux, et celui qui justifiait de les séparer : ses deux
 * exercices sont tirés à l'affichage, et l'inscription se prépare à chaque
 * ouverture.
 */
final readonly class PanneauDeLaMaisonDesScribes implements FournisseurDePanneau
{
    public function __construct(
        private Dechiffrage $dechiffrage,
        private Enquetes $enquetes,
        private EnigmesDuLieu $enigmes,
        private MissionCatalogue $missions,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::MaisonDesScribes->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $mission = $this->missions->de($partie);
        $inscription = $this->dechiffrage->proposition($partie);

        // Les deux exercices d'écriture, tirés à l'affichage : la graine part
        // avec le formulaire, et le serveur recompose la même série pour
        // corriger. Rien n'est stocké entre les deux.
        $graineDesSons = random_int(1, \PHP_INT_MAX >> 33);
        $graineDeLecture = random_int(1, \PHP_INT_MAX >> 33);

        return [
            'graineDesSons' => $graineDesSons,
            'serieDesSons' => ExerciceDesSons::serie($ville, $graineDesSons),
            'graineDeLecture' => $graineDeLecture,
            'lectureDeCartouche' => LectureDeCartouche::exercice($graineDeLecture),
            'questionsDesSons' => ExerciceDesSons::QUESTIONS,
            // Ce que la ville sait d'écriture, et ce que ça lui rapporte : un
            // apprentissage qui ne se voit pas n'est pas poursuivi.
            'erudition' => PalierDErudition::pour($ville, $partie->getCycle()),
            'nombreDeSignesAppris' => PalierDErudition::signesConnus($ville, $partie->getCycle()),
            // Le nom du jeu, en vrais signes : jamais un glyphe tapé à la main.
            'motDeNiout' => LeconDeNiout::motEcrit(),
            'totalDesSignes' => PalierDErudition::signesEnTout(),
            'maisonDesScribesDressee' => true,
            // La clé de lecture, et l'alphabet des scribes : la seconde piste
            // du doc 10, celle des sons. Elle ne se mélange jamais à la clé de
            // lecture, alors même que six dessins leur sont communs.
            'cleDeLecture' => CleDeLecture::pour($ville, $partie->getCycle()),
            'alphabet' => AlphabetDesScribes::pour($ville),
            'prochainSigneDeLAlphabet' => AlphabetDesScribes::prochainSigne($ville),
            'signesDeLAlphabetEnTout' => \count(SigneAlphabetique::cases()),
            'signesParNiveauDAlphabet' => AlphabetDesScribes::SIGNES_PAR_NIVEAU,
            // La leçon fondatrice, mêlée au rendu comme les jetons du
            // déchiffrage : dans l'ordre, elle se lirait dans la source.
            'leconDeNiout' => Melange::leconDeNiout(),
            'nioutDejaEcrite' => $ville->aEcritNiout(),
            // Le nom de la famille écrit à la manière des musées : la
            // transcription est **entière** dès la Maison des scribes dressée,
            // et l'écran montre en retrait les signes pas encore appris.
            'nomTranscrit' => TranscriptionDuNom::pour($partie->getFamille()->getNom()),
            'signesConnus' => AlphabetDesScribes::pour($ville),
            'prochainSigne' => CleDeLecture::prochainSigne($ville, $partie->getCycle()),
            'signesEnTout' => \count(SymboleHieroglyphique::cases()),
            'inscription' => $inscription,
            // Les jetons sont mélangés **au rendu** : les laisser dans l'ordre
            // gravé donnerait la réponse par la seule lecture du HTML.
            'melange' => $inscription instanceof Inscription ? Melange::signesDe($inscription) : [],
            'inscriptionsLues' => \count($ville->inscriptionsDechiffrees()),
            // La stèle réelle du pharaon commanditaire (doc 09). Elle n'est
            // **pas** l'inscription qu'on déchiffre : celle-ci reste un rébus,
            // la stèle est ce à quoi elle fait écho.
            'stele' => null !== $mission ? SteleHistorique::pourLePharaon($mission->pharaon) : null,
            'filRouge' => FilRouge::court($partie) ? FilRouge::acte($partie) : null,
            'dossiers' => array_map(
                static fn ($dossier): array => [
                    'dossier' => $dossier,
                    'peutConclure' => $dossier->peutConclure($partie->getCycle()),
                    // Mélangées au rendu : la bonne conclusion est la première
                    // du catalogue, et se lirait sinon dans la source.
                    'conclusions' => Melange::liste($dossier->getEnquete()->conclusions()),
                ],
                $this->enquetes->dossiers($partie),
            ),
            // **Un onglet, un bâtiment** : les énigmes se rangent là où on les
            // entend, c'est `Enigme::lieu()` qui décide, pas l'écran.
            'enigmesDesScribes' => $this->enigmes->pour($partie, TypeDeBatiment::MaisonDesScribes),
        ] + $this->direction->donnees($partie);
    }
}
