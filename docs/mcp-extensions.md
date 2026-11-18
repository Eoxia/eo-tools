# Réglages des extensions par l'IA (EO Tools MCP)

L'IA connectée à EO Tools peut lire et modifier les réglages de certaines extensions, par exemple pour la checklist de livraison d'un site. Ce document explique où se trouve le code, ce que chaque extension permet et comment en ajouter une.

## 1. Principes

- **Pas d'accès libre aux options.** Chaque extension prise en charge a un *adaptateur* qui expose une liste fermée de réglages, avec leur type et leurs limites. Il les enregistre avec l'API de l'extension, comme le ferait son propre écran de réglages.
- **Tout ou rien.** Toutes les valeurs sont vérifiées avant l'enregistrement. Si une seule est refusée, rien n'est modifié.
- **Journal.** Chaque changement est noté avec son ancienne valeur, dans le même journal que les réglages WordPress (option `eo_tools_mcp_settings_log`), dans le contexte `extension#cible`.
- **Les secrets ne passent jamais par l'IA.** Les mots de passe et clés secrètes sont saisis par un administrateur dans *EO Tools > MCP > Autorisations*, section « Clés secrètes des extensions ». L'IA voit seulement si la clé est renseignée, et le lien à donner à l'utilisateur.
- **Ce qui revient à un humain reste à un humain.** C'est le cas de la licence, de la connexion d'un compte, et de toute modification des fichiers du serveur (`.htaccess`, `wp-config.php`, `.user.ini`). L'adaptateur l'indique dans `status`, avec le lien de l'écran à utiliser.
- **L'extension doit être active.** L'IA l'installe (`eo-tools/install-plugin`), puis demande l'autorisation de l'activer (`eo-tools/request-elevation`).

## 2. Où est le code

| Fichier | Rôle |
|---|---|
| `includes/mcp/class-eotools-mcp-plugin-settings.php` | Registre des adaptateurs (filtre `eo_tools_mcp_plugin_adapters`), journal, formulaire des clés secrètes |
| `includes/mcp/plugins/class-eotools-mcp-plugin-adapter.php` | Classe de base : vérification des valeurs, comparaison avant/après, secrets |
| `includes/mcp/plugins/class-eotools-mcp-plugin-*.php` | Un adaptateur par extension |
| `includes/mcp/abilities/class-eotools-mcp-ability-get-plugin-settings.php` | Outil `eo-tools/get-plugin-settings` (lecture) |
| `includes/mcp/abilities/class-eotools-mcp-ability-update-plugin-settings.php` | Outil `eo-tools/update-plugin-settings` (niveau « Sensible ») |
| `includes/mcp/class-eotools-mcp-cookies.php` | Scan, liste et validation des cookies d'EO Tools, pour les outils `eo-tools/scan-cookies` et `eo-tools/update-cookies` (le scan lui-même est dans `includes/api-eo-detailed-scan.php`, partagé avec l'écran) |
| `includes/admin/views/html-admin-mcp-elevation.php` | Onglet « Autorisations », dont la section des clés secrètes |

Le titre SEO, la méta description et les autres champs SEO d'une page ou d'une catégorie ne sont pas des réglages d'extension. Ils passent par les passerelles SEO (section 5).

## 3. Extensions prises en charge

| Extension (slug) | L'IA peut régler | Reste à un humain |
|---|---|---|
| Cookies d'EO Tools (`eo-tools-cookies`) | Activation du gestionnaire (bandeau et blocage des cookies tiers avant consentement), durée de conservation des choix, icônes. Avec les outils `eo-tools/scan-cookies` (scan détaillé, repris par étapes) et `eo-tools/update-cookies` (liste des cookies et « Validation Admin »), notés dans le rapport de consentements comme depuis l'écran | — |
| Contact Form 7 (`contact-form-7`) | Par formulaire : destinataire, expéditeur, objet, corps, réponse automatique, modèle, captcha (image Really Simple CAPTCHA, reCAPTCHA v3, Turnstile) et clés de site | Clés secrètes reCAPTCHA / Turnstile (section « Clés secrètes ») |
| Easy WP SMTP (`easy-wp-smtp`) | Envoi par serveur SMTP : hôte, port, chiffrement, identifiant, adresse et nom d'expéditeur (forcés ou non) | Mot de passe SMTP (section « Clés secrètes »), e-mail de test |
| GDPR Cookie Consent, WebToffee (`webtoffee-cookie-consent`) | Langue du bandeau, boutons Tout accepter / Tout refuser / Personnaliser, durée du consentement, blocage des scripts avant consentement, journal des consentements | Scan des cookies (compte WebToffee) |
| Wordfence (`wordfence`) | Mode du pare-feu, protection force brute (seuils, durées), adresses des alertes | Assistant de démarrage (licence), « Optimiser le pare-feu » (fichiers du serveur) |
| All In One WP Security (`all-in-one-wp-security-and-firewall`) | Verrouillage des connexions (essais, période, durées). Désactiver le renommage de la page de connexion et la détection de modification des fichiers. L'état du pare-feu AIOS est signalé, pour éviter un conflit avec Wordfence | Pare-feu AIOS (`.htaccess`), « Disable PHP file editing » (`wp-config.php`) |
| Yoast SEO (`wordpress-seo`) | Configuration initiale : organisation ou personne, nom, logo, nom du site, réseaux sociaux, suivi d'utilisation, configuration marquée comme terminée | — |
| SEOKEY (`seo-key-pro`) | Types de contenus et taxonomies montrés aux moteurs, titre et description de l'accueil, type de propriétaire du site (données structurées), Open Graph, optimisations (pagination des auteurs et des commentaires, liens « Répondre », contenus noindex retirés des listes, flux RSS secondaires) | Assistant de démarrage (il crée `robots.txt` sur le serveur), détail des données structurées, licence, Search Console |

Rien à configurer côté site pour MainWP Child (le site est ajouté depuis le tableau de bord MainWP) ni pour Site Kit by Google (il faut un compte Google).

## 4. Ajouter une extension

1. Créer `includes/mcp/plugins/class-eotools-mcp-plugin-<slug>.php`, avec une classe `Eotools_Mcp_Plugin_<Slug>` qui étend `Eotools_Mcp_Plugin_Adapter`. Le chargement automatique trouve le fichier d'après le nom de la classe.
2. Écrire les méthodes :
   - `get_slug()` (slug wordpress.org), `get_label()`, `is_available()` (l'API de l'extension est chargée) ;
   - `get_schema()` : les réglages exposés. Types : `bool`, `int` (`min`, `max`), `enum` (`choices`), `string`, `text`, `email`, `emails`, `url`, `urls`. Le libellé (`label`) est lu par l'IA : il doit dire quoi mettre ;
   - `read_settings( $target )` : les valeurs actuelles, avec les mêmes noms et types que le schéma ;
   - `write_settings( $clean, $target )` : l'enregistrement avec l'API de l'extension. Renvoie des notes pour l'IA.
3. Si besoin :
   - `check_values()` pour les règles entre réglages ;
   - `validate_value()` pour un format particulier (appeler le parent pour le reste) ;
   - `get_status()` pour l'état en lecture seule et les liens des écrans réservés à un humain ;
   - `get_secrets()`, `has_secret()`, `is_secret_needed()`, `save_secret()` pour les secrets.
4. Ajouter la classe à la liste de `Eotools_Mcp_Plugin_Settings::get_adapters()`. Une autre extension peut aussi l'ajouter avec le filtre `eo_tools_mcp_plugin_adapters`.

Avant d'exposer un réglage, vérifier dans le code de l'extension comment son écran l'enregistre. Si l'écran écrit dans un fichier du serveur, le réglage ne doit pas être exposé : le signaler dans `status`.

## 5. Passerelles SEO (Yoast SEO, SEOKEY…)

Les extensions SEO ne sont pas des extensions Eoxia : EO Tools ne modifie jamais leur code. Il passe par ses propres **passerelles**, qui écrivent les données comme l'écran de l'extension (mêmes métas, mêmes options, mêmes fonctions publiques).

| Fichier | Rôle |
|---|---|
| `includes/mcp/seo/class-eotools-mcp-seo-provider.php` | Classe de base : WordPress sans extension SEO, champs SEO typés (`get_field_definitions()`), lecture et écriture génériques en métas |
| `includes/mcp/seo/class-eotools-mcp-seo-provider-yoast.php` | Yoast SEO |
| `includes/mcp/seo/class-eotools-mcp-seo-provider-seokey.php` | SEOKEY |
| `includes/mcp/class-eotools-mcp-seo.php` | Audits (site, contenus, catégories, 404) |
| `includes/mcp/class-eotools-mcp-seo-changes.php` | Modifications SEO en attente qui ne sont pas des contenus (redirections, catégories), validées dans l'onglet « À valider » |
| `includes/mcp/class-eotools-mcp-redirections.php` | Redirections 301 du module 404 d'EO Tools |

Champs SEO écrits par chaque passerelle :

| Champ | Contenus : Yoast | Contenus : SEOKEY | Catégories : Yoast | Catégories : SEOKEY |
|---|---|---|---|---|
| `title`, `description` | ✓ | ✓ | ✓ | ✓ |
| `focus_keyword` | ✓ | ✓ | ✓ | — |
| `noindex` | ✓ | ✓ | ✓ | ✓ |
| `canonical`, `og_title`, `og_description`, `og_image_id` | ✓ | — | ✓ | — |
| `cornerstone` | ✓ | — | — | — |

- **Contenus** : champ `seo` de `eo-tools/update-draft` (brouillon) et `eo-tools/propose-update` (contenu publié, appliqué par un humain). Le schéma est le même quelle que soit l'extension active, car les clients MCP gardent longtemps la liste des outils. Un champ que l'extension active ne gère pas est ignoré et signalé dans `warnings`.
- **Détail de l'analyse** (`content.analysis` de `eo-tools/audit-post-seo`) :
  - Yoast SEO n'enregistre que ses scores globaux. Le script `assets/js/seo-analysis.js` lit le détail des critères dans le store `yoast-seo/editor` quand le contenu est enregistré dans l'éditeur (Gutenberg ou classique). La classe `Eotools_Mcp_Seo_Analysis` le garde dans la méta `_eo_tools_seo_analysis`.
  - L'analyse est marquée `stale` quand le titre, le contenu, l'extrait, le slug ou les métas SEO ont changé depuis.
  - SEOKEY : problèmes de son dernier audit, au même format (`seo_results` avec `id`, `rating`, `text`).
- **Aperçu des propositions** : les modifications qui ne se voient pas sur la page (champs SEO, slug, extrait, catégories, étiquettes) sont listées avant → après au-dessus des boutons « Appliquer / Rejeter ». À l'application d'une proposition, les champs SEO sont copiés avant l'enregistrement du contenu, pour que l'extension mette à jour ses propres données (index, plan de site).
- **Yoast SEO** : sur un contenu publié, la passerelle réécrit les valeurs avec l'ability de Yoast `yoast-seo/update-post-seo-data` (Yoast 28 et plus), qui reconstruit l'index de Yoast. Ces abilities de Yoast ne sont pas proposées telles quelles à l'IA : elles modifient directement un contenu publié, sans validation. Pour les catégories, la passerelle utilise `WPSEO_Taxonomy_Meta::set_values()` (avec toutes les valeurs actuelles, car Yoast remet à zéro les champs absents), puis enregistre le terme pour que Yoast reconstruise son index.
- **Catégories, étiquettes…** : section `terms` de `eo-tools/audit-site-seo`, types `term_*` de `eo-tools/list-seo-issues`, corrections avec `eo-tools/propose-term-seo` (niveau « Sensible », appliquées par un humain).
- **Redirections** : toujours dans EO Tools (table `eotools_redirections`), quelle que soit l'extension SEO. `eo-tools/propose-redirections` les propose, un humain les applique. Quand une proposition change le slug d'un contenu publié, l'ancienne adresse (et celles des sous-pages) est redirigée à l'application. Une redirection vers une adresse déjà redirigée est refusée, et les anciennes redirections vers l'adresse redirigée sont mises à jour (pas de chaîne).
- **Textes alternatifs** : `eo-tools/list-seo-issues` avec le type `media_without_alt`, correction avec `eo-tools/update-media` (niveau « Écriture » : seule la médiathèque change).

Pour prendre en charge une autre extension SEO : étendre `Eotools_Mcp_Seo_Provider` (au minimum `get_id()`, `get_name()`, `is_active()`, `supports()`, `get_meta_keys()`, les lectures du titre et de la description, et les méthodes `*_term_*` pour les catégories), puis l'ajouter avec le filtre `eo_tools_seo_providers`. Ne jamais tester une extension SEO directement dans `Eotools_Mcp_Seo`.
