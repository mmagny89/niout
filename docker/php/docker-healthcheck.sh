#!/bin/sh
# docker-healthcheck — aucune option. Interroge l'endpoint /health servi par
# Caddy (configuration nominale ou de secours, les deux l'exposent). N'utilise
# que le binaire php, present dans toutes les images, y compris l'image de
# production rootless.
#
# Cible directement 127.0.0.1:443 en HTTPS plutot que :80 : Caddy redirige
# systematiquement le HTTP vers le HTTPS (y compris pour "localhost", via son
# autorite de certification interne), et la verification suivrait alors une
# redirection vers un hote different. Le SNI/Host sont forces sur
# SERVER_NAME pour que Caddy route vers le bon site et presente le bon
# certificat ; la verification du certificat est desactivee car il s'agit
# d'un appel en boucle locale, jamais expose au reseau.
set -e

php -r '
	$host = getenv("SERVER_NAME");
	if ($host === false || $host === "") {
		$host = "localhost";
	}

	$context = stream_context_create([
		"http" => [
			"timeout" => 3,
			"ignore_errors" => true,
			"header" => "Host: {$host}\r\n",
		],
		"ssl" => [
			"verify_peer" => false,
			"verify_peer_name" => false,
			"allow_self_signed" => true,
			"peer_name" => $host,
		],
	]);

	$body = @file_get_contents("https://127.0.0.1/health", false, $context);
	if ($body === false) {
		fwrite(STDERR, "docker-healthcheck: no response on :443/health\n");
		exit(1);
	}

	$status = null;
	foreach ($http_response_header ?? [] as $header) {
		if (preg_match("#^HTTP/\S+\s+(\d+)#", $header, $m)) {
			$status = (int) $m[1];
		}
	}

	if ($status !== null && $status >= 500) {
		fwrite(STDERR, "docker-healthcheck: HTTP {$status} on :443/health\n");
		exit(1);
	}

	exit(0);
'
