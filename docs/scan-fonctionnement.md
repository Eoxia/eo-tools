# Fonctionnement du Scan Détaillé des Cookies (EO Tools)

Ce document décrit l'architecture et le fonctionnement du scan détaillé des cookies intégré au plugin EO Tools. Ce scan permet de parcourir la base de données (posts, pages, types de contenu personnalisés) ainsi que les en-têtes et pieds de page pour identifier les cookies potentiellement définis par le site.

## 1. Objectif du Scan

Le but de ce scan est d'identifier :
1. Les cookies définis côté serveur via les en-têtes HTTP (`Set-Cookie`).
2. Les cookies définis via des scripts tiers intégrés dans le code HTML (Google Analytics, Facebook Pixel, etc.), via une recherche par expression régulière (RegEx).

## 2. Architecture Technique (Traitement par lots / Batching)

Le scan de l'intégralité d'un site web peut être une opération longue qui risque de provoquer une erreur de délai d'attente (timeout) sur le serveur. Pour pallier ce problème, le scan est conçu de manière asynchrone via des appels AJAX successifs.

### 2.1 Table de file d'attente (`wp_eotools_scan_log`)
Une table de base de données dédiée, `wp_eotools_scan_log`, est utilisée pour stocker la file d'attente. 
- Lors de l'initialisation du scan, toutes les URLs publiques des contenus publiés (Articles, Pages, CPTs) ainsi que les Headers/Footers sont insérées dans cette table avec le statut `pending`.
- Chaque lot met à jour l'état de l'URL à `processing` puis à `completed`.

### 2.2 Processus AJAX
1. **Initialisation (`eo_tools_start_detailed_scan`)** : Vide l'ancienne file d'attente, compte les éléments, et insère les nouvelles URLs.
2. **Traitement d'un Lot (`eo_tools_process_scan_batch`)** :
   - Récupère un nombre limité d'URLs (ex: 5).
   - Pour chaque URL, exécute une requête HTTP `GET` (`wp_remote_get`).
   - Analyse les en-têtes et le corps de la réponse.
   - Enregistre les cookies trouvés dans la colonne `cookies_found` au format JSON.
   - Renvoie la progression à l'interface Javascript.

## 3. Reprise après erreur (Resumable Scan)
Si la requête AJAX échoue (ex: perte de connexion, timeout serveur), le script JavaScript (`cookies-detailed-scan.js`) patiente 5 secondes puis relance automatiquement la fonction de traitement (`processBatch()`). Étant donné que le lot en échec reste ou est partiellement mis à jour, le système reprend naturellement là où il s'est arrêté en interrogeant à nouveau les URLs avec le statut `pending`.

## 4. Console de Log en temps réel
L'interface d'administration inclut une console simulant un terminal. Les événements (succès, erreurs, informations) y sont injectés en temps réel au format `[Mois Jour Heure:type] Message`, offrant une excellente visibilité sur les actions du scanner (ex: URLs scannées, cookies trouvés).

## 5. Arborescence de documentation par page
Si vous souhaitez documenter le comportement de pages spécifiques face aux cookies, nous vous recommandons de conserver cette structure dans ce dossier `docs/` :
- `docs/pages/`
  - `header-footer.md` (Analyse globale du site)
  - `articles.md` (Comportement spécifique aux articles de blog)
  - `woocommerce.md` (Si applicable, cookies spécifiques au panier)
- `docs/regex-patterns.md` (Liste des signatures de scripts identifiées par le scanner)
