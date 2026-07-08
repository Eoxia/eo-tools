# EO Tools - Landing Pages

Version allégée du plugin EO Tools, dédiée aux **pages d'atterrissage** WordPress.

## Fonctionnalités

- **Prochainement** : page « Bientôt disponible » affichée aux visiteurs pendant la construction du site (les administrateurs voient le site normalement).
- **Maintenance** : page d'indisponibilité temporaire, renvoyant un code HTTP `503`.
- **404** : page d'erreur 404 personnalisée avec bouton de retour à l'accueil.

Chaque page est personnalisable : titre, texte (mise en forme simplifiée), style visuel (Minimaliste, Dégradé, Effet verre) et couleurs. Les modes Prochainement et Maintenance sont mutuellement exclusifs, et un badge dans la barre d'administration signale le mode actif.

## Respect des standards

- Aucune ressource externe chargée (polices système, aucun appel réseau sortant).
- Textes internationalisés (text domain `eo-tools`).
- Entrées assainies, sorties échappées, vérification des nonces et des capacités.
- Nettoyage des options à la désinstallation (`uninstall.php`), compatible multisite.

## Installation

1. Copiez le dossier `eo-tools` dans `wp-content/plugins/`.
2. Activez l'extension depuis le menu **Extensions**.
3. Ouvrez le menu **Pages d'atterrissage** pour configurer et activer les modes.

---
*Plugin développé et maintenu par Eoxia.*
