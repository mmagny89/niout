#!/bin/sh
# neutralize-app-env — aucune option. Contrat fixe (conventions, section 7) :
# desamorce dans les fichiers .env committes de l'application tout ce dont la
# valeur de verite vit ailleurs. Rejouable apres chaque installation de paquet
# susceptible de deposer une recette (ex. la recette Doctrine qui ecrit
# DATABASE_URL, la recette framework-bundle qui ecrit APP_SECRET).
#
# Trois traitements distincts, pour trois raisons distinctes :
#
#   1. Variables INJECTEES par le stack Docker (compose.yml ou ENV du
#      Dockerfile). On les COMMENTE : la valeur du conteneur fait foi, et une
#      valeur concurrente dans app/.env serait masquee en conteneur et fausse
#      hors conteneur.
#
#   2. APP_SECRET. Rien ne l'injecte en developpement — Symfony en a besoin,
#      il doit donc rester DECLARE. Mais la recette Flex y ecrit un secret
#      REEL, dans un fichier committe : sur un depot public, il est publie
#      (incident reel du 2026-08-27, valeur revoquee). On le VIDE dans les
#      fichiers committes et on en genere un vrai dans .env.local, ignore par
#      git. Le commenter ne suffirait pas : Symfony echouerait au demarrage.
#
#   3. Les memes variables injectees, DECLAREES dans app/.env.test. Les tests ne
#      passent pas par Compose — ni ceux qu'on lance a la main, ni ceux de la
#      CI — et une variable commentee au point 1 n'existe alors nulle part : la
#      compilation du conteneur echoue sur « Environment variable not found »,
#      dans tous les jobs, avec un message qui ne parle jamais d'email. Les
#      valeurs y sont inertes : un test ne doit joindre personne.
set -e

APP_DIR=/app

if [ ! -d "$APP_DIR" ]; then
	exit 0
fi

# --- 1. Variables injectees par le conteneur -------------------------------

ENV_FILE="$APP_DIR/.env"
# MAILER_DSN et MAILER_FROM sont injectees par compose.yml depuis 2026-09-12 :
# la recette Flex depose MAILER_DSN=null://null, un transport qui accepte chaque
# message et le jette sans un mot. Voir la section 8b des conventions.
INJECTED_VARS="DATABASE_URL APP_ENV MAILER_DSN MAILER_FROM"

if [ -f "$ENV_FILE" ]; then
	for var in $INJECTED_VARS; do
		awk -v var="$var" '
			BEGIN { pattern = "^" var "=" }
			$0 ~ pattern {
				print "# " $0 "  # source of truth: compose.yml (neutralized by neutralize-app-env)"
				next
			}
			{ print }
		' "$ENV_FILE" > "${ENV_FILE}.neutralized"
		mv "${ENV_FILE}.neutralized" "$ENV_FILE"
	done
fi

# --- 2. APP_SECRET ---------------------------------------------------------
#
# ORDRE IMPORTANT : on genere le secret de remplacement AVANT de vider les
# fichiers committes. L'inverse (vider puis generer) laisse l'application sans
# secret du tout si la generation echoue, et `set -e` interrompt le script
# juste apres la destruction. Ici, un echec de generation s'arrete avant
# d'avoir touche quoi que ce soit.
#
# .env.test est volontairement EXCLU des deux etapes : Symfony y livre une
# valeur fixe et publique par convention, et les tests ont besoin d'un secret
# deterministe. Ce n'est pas une fuite, c'est le comportement attendu.

FICHIERS_COMMITTES="$APP_DIR/.env $APP_DIR/.env.dev"
LOCAL_FILE="$APP_DIR/.env.local"

# Y a-t-il seulement quelque chose a faire ? Si aucun fichier committe ne porte
# de valeur non vide, on ne genere rien et on ne reecrit rien : c'est ce qui
# rend le script rejouable sans effet de bord (conventions, section 7).
a_neutraliser=''
for fichier in $FICHIERS_COMMITTES; do
	[ -f "$fichier" ] || continue
	if grep -qE '^APP_SECRET=.+' "$fichier"; then
		a_neutraliser="$a_neutraliser $fichier"
	fi
done

if [ -n "$a_neutraliser" ]; then
	# Un secret reel, une seule fois. `php -r` plutot qu'`openssl` : PHP est le
	# seul binaire dont la presence dans l'image est garantie par construction.
	if ! grep -qE '^APP_SECRET=.+' "$LOCAL_FILE" 2>/dev/null; then
		if ! secret="$(php -r 'echo bin2hex(random_bytes(16));')" || [ -z "$secret" ]; then
			echo "neutralize-app-env: impossible de generer APP_SECRET (php absent ou en echec)." >&2
			echo "  Les fichiers committes n'ont PAS ete modifies : l'application reste demarrable." >&2
			echo "  Relancer ce script depuis le conteneur, ou renseigner .env.local a la main." >&2
			exit 1
		fi
		printf 'APP_SECRET=%s\n' "$secret" >> "$LOCAL_FILE"
		echo "neutralize-app-env: APP_SECRET genere dans .env.local" >&2
	fi

	for fichier in $a_neutraliser; do
		awk '
			/^APP_SECRET=/ {
				print "# Vide volontairement : ce fichier est committe. Le secret reel vit"
				print "# dans .env.local (ignore par git), genere par neutralize-app-env."
				print "APP_SECRET="
				next
			}
			{ print }
		' "$fichier" > "${fichier}.neutralized"
		mv "${fichier}.neutralized" "$fichier"
		echo "neutralize-app-env: APP_SECRET vide dans $(basename "$fichier")" >&2
	done
fi

# --- 3. Valeurs inertes pour les tests -------------------------------------
#
# Ce que le point 1 a commente dans app/.env doit exister quand meme hors
# conteneur. `null://null` est ici le bon transport, et non le piege qu'il est
# en production : un test ne doit joindre personne.

TEST_FILE="$APP_DIR/.env.test"

if [ -f "$TEST_FILE" ]; then
	ajoutees=''
	for ligne in 'MAILER_DSN=null://null' 'MAILER_FROM=noreply@localhost'; do
		var="${ligne%%=*}"
		if ! grep -qE "^${var}=" "$TEST_FILE"; then
			[ -n "$ajoutees" ] || printf '\n# Injectees par compose.yml en conteneur, absentes hors conteneur : sans\n# elles, la compilation du conteneur echoue sur « Environment variable not\n# found » (conventions, section 8b). Valeurs inertes : un test ne joint\n# personne.\n' >> "$TEST_FILE"
			printf '%s\n' "$ligne" >> "$TEST_FILE"
			ajoutees="$ajoutees $var"
		fi
	done
	[ -z "$ajoutees" ] || echo "neutralize-app-env: declarees dans .env.test :$ajoutees" >&2
fi
