# Architektonická specifikace — základ

## Účel
Tento dokument definuje základní architekturu backendu projektu. Slouží jako referenční podklad pro implementaci, nasazení a provoz.

## Rozsah
- Backend aplikace
- Datová vrstva
- Požadavky na provoz a zálohování
- Bezpečnostní a provozní požadavky

## Cíle
- Zajistit škálovatelnost a udržovatelnost kódu
- Definovat konzistentní prostředí pro nasazení
- Stanovit požadavky na dostupnost a obnovu dat

## Požadované technologie
- PHP 8.5
- Symfony 7.4
- MariaDB 11.4

> Poznámka: v této specifikaci jsou uvedeny pouze výše schválené technologie.

## Hlavní komponenty (logický přehled)
- HTTP API: rozhraní pro klientské aplikace a frontend
- Aplikační vrstva: obchodní logika, služby a validace
- Persistenční vrstva: MariaDB 11.4 pro relační data
- Migrace a správa schémat: procesy pro verzování DB (implementace doplněna později)

## Datová architektura
- Primární úložiště: MariaDB 11.4
- Modelování schématu: relační model navržen podle doménových entit
- Zálohování a obnova: plán záloh a testované postupy obnovení (detaily doplnit)

## Bezpečnost (požadavky)
- Vynucení zabezpečené komunikace mezi klientem a serverem (HTTPS)
- Bezpečné uložení a správa tajemství (DB přihlašovací údaje apod.)
- Princip nejmenších práv pro přístup k DB a službám
- Vstupní validace a ochrana proti běžným útokům na aplikační úrovni

## Provoz a nasazení
- Prostředí: `dev`, `beta`, `prod`
- Automatizace nasazení: CI/CD pipeline (konkrétní nástroje doplnit později)
- Monitorování dostupnosti a výkonu (metriky, logy) — detaily doplnit

- Containerizace: Použití Dockeru pro lokální vývoj, CI a konzistentní prostředí nasazení. Minimální doporučení:
	- Vytvořit Dockerfile pro PHP (PHP 8.5) a docker-compose.yml pro lokální vývojní stack (PHP-FPM, Nginx, MariaDB).
	- Používat kontejnery i v CI pro izolované sestavení a testy, a verzovat image nebo tagy podle CI/CD pipeline.
	- Zajistit, aby konfigurace citlivých údajů byla mimo image (env proměnné, Docker secrets nebo CI secret store).

## Zálohy a obnova
- Definovat RPO/RTO
- Plán pro pravidelné zálohy databáze a ověřené restore procedury

## Dokumentace a runbooky
- Diagram architektury (doplnit)
- Runbook pro nasazení
- Runbook pro obnovu z zálohy

## Další kroky
- Doplnení implementačních detailů (migrace, CI/CD, monitoring)
- Vytvoření architektonického diagramu (C4 nebo podobný)
- Upřesnění RTO/RPO a backup retention

---

TODO: doplnit konkrétní implementační kroky a nástroje pouze po schválení.
