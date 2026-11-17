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
| `includes/admin/views/html-admin-mcp-elevation.php` | Onglet « Autorisations », dont la section des clés secrètes |

Le titre SEO, la méta description et la requête cible d'une page ne sont pas des réglages d'extension. Ce sont des champs du contenu : le champ `seo` de `eo-tools/update-draft` (brouillon) et de `eo-tools/propose-update` (page publiée, appliqué par un humain). Ils sont écrits par le fournisseur SEO actif (`includes/mcp/seo/`, Yoast SEO ou SEOKEY).

## 3. Extensions prises en charge

| Extension (slug) | L'IA peut régler | Reste à un humain |
|---|---|---|
| Contact Form 7 (`contact-form-7`) | Par formulaire : destinataire, expéditeur, objet, corps, réponse automatique, modèle, captcha (image Really Simple CAPTCHA, reCAPTCHA v3, Turnstile) et clés de site | Clés secrètes reCAPTCHA / Turnstile (section « Clés secrètes ») |
| Easy WP SMTP (`easy-wp-smtp`) | Envoi par serveur SMTP : hôte, port, chiffrement, identifiant, adresse et nom d'expéditeur (forcés ou non) | Mot de passe SMTP (section « Clés secrètes »), e-mail de test |
| GDPR Cookie Consent, WebToffee (`webtoffee-cookie-consent`) | Langue du bandeau, boutons Tout accepter / Tout refuser / Personnaliser, durée du consentement, blocage des scripts avant consentement, journal des consentements | Scan des cookies (compte WebToffee) |
| Wordfence (`wordfence`) | Mode du pare-feu, protection force brute (seuils, durées), adresses des alertes | Assistant de démarrage (licence), « Optimiser le pare-feu » (fichiers du serveur) |
| All In One WP Security (`all-in-one-wp-security-and-firewall`) | Verrouillage des connexions (essais, période, durées). Désactiver le renommage de la page de connexion et la détection de modification des fichiers. L'état du pare-feu AIOS est signalé, pour éviter un conflit avec Wordfence | Pare-feu AIOS (`.htaccess`), « Disable PHP file editing » (`wp-config.php`) |
| Yoast SEO (`wordpress-seo`) | Configuration initiale : organisation ou personne, nom, logo, nom du site, réseaux sociaux, suivi d'utilisation, configuration marquée comme terminée | — |

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
