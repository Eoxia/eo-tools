=== EO Tools - Landing Pages ===
Contributors: eoxia
Tags: maintenance, coming soon, 404, landing page, holding page
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Affichez des pages d'atterrissage personnalisées : Prochainement, Maintenance et 404.

== Description ==

EO Tools - Landing Pages est une version allégée dédiée à l'affichage de pages
d'atterrissage personnalisées sur votre site WordPress.

Trois modes sont disponibles :

* **Prochainement** : affiche une page « Bientôt disponible » aux visiteurs
  pendant la construction du site. Les administrateurs continuent de voir le site.
* **Maintenance** : affiche une page d'indisponibilité temporaire et renvoie un
  code HTTP 503 aux visiteurs et aux moteurs de recherche.
* **404** : remplace la page d'erreur 404 par un écran personnalisé avec un bouton
  de retour à l'accueil.

Chaque page est personnalisable : titre, texte (mise en forme simplifiée),
style visuel (Minimaliste, Dégradé, Effet verre) et couleurs.

Les modes Prochainement et Maintenance sont mutuellement exclusifs. Un badge
dans la barre d'administration signale à tout moment le mode actif.

= Confidentialité =

Le plugin ne charge aucune ressource externe et n'effectue aucun appel réseau
sortant. Aucune donnée personnelle n'est collectée.

== Installation ==

1. Téléversez le dossier `eo-tools` dans `/wp-content/plugins/`.
2. Activez l'extension via le menu « Extensions » de WordPress.
3. Rendez-vous dans le menu « Pages d'atterrissage » pour configurer et activer
   les modes souhaités.

== Frequently Asked Questions ==

= Les administrateurs voient-ils la page de maintenance ? =

Non. Les utilisateurs disposant de la capacité `manage_options` accèdent
normalement au site. Un bouton de prévisualisation permet de voir chaque page.

= Puis-je activer Prochainement et Maintenance en même temps ? =

Non, ces deux modes sont mutuellement exclusifs. Activer l'un désactive l'autre.

== Changelog ==

= 1.0.0 =
* Version initiale allégée : pages Prochainement, Maintenance et 404.
