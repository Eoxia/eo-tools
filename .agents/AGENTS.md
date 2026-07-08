# AGENTS.md

## Context

This repository contains a WordPress plugin.

Objective:
- Adhere to WordPress standards.
- Ensure backward compatibility.
- Produce maintainable code.
- Minimize external dependencies.

Before making any modifications, analyze the impact on:
- WordPress Core
- Multisite
- Translations
- Security
- Performance

---

## Architecture

Code organization:

plugin/
â”œâ”€â”€ admin/
â”œâ”€â”€ public/
â”œâ”€â”€ includes/
â”œâ”€â”€ assets/
â”œâ”€â”€ languages/
â”œâ”€â”€ templates/
â”œâ”€â”€ tests/

Rules:

- Never place business logic in templates.
- Templates must only display data.
- All business logic must be placed in `/includes`.
- All administration logic must be placed in `/admin`.

---

## WordPress Standards

Adhere to:

- WordPress Coding Standards
- PSR-12 when compatible
- PHP 8.1+

Always use:

sanitize_text_field()
sanitize_email()
sanitize_key()
wp_nonce_field()
check_admin_referer()
wp_verify_nonce()

Never trust data from:

- `$_POST`
- `$_GET`
- `$_REQUEST`
- `$_COOKIE`

---

## Internationalization

All displayed text must be translatable.

Use:

__()
_e()
esc_html__()
esc_attr__()

Text domain:

eo-tools

Example:

__('Settings saved', 'eo-tools')

---

## Security

Always:

- Check user capabilities.
- Verify nonces.
- Escape outputs.

Use:

esc_html()
esc_attr()
esc_url()
wp_kses_post()

Never:

- Disable permission checks.
- Execute raw SQL without preparation.

Systematically use:

$wpdb->prepare()

---

## Database

Before any modification:

1. Check if a WordPress API already exists.
2. Avoid custom tables unless absolutely necessary.
3. Prefer:
   - Options API
   - Post Meta
   - User Meta
   - Custom Post Types

---

## JavaScript

Prioritize:

- Vanilla JS
- WordPress APIs
- React only if already present

Do not add extra JS frameworks without justification.

---

## CSS

Priority:

1. Existing WordPress classes
2. Plugin CSS
3. No external CSS frameworks

Limit overly specific selectors.

---

## Performance

Before each commit:

Check:

- Number of SQL queries
- External API calls
- Asset loading

Only load:

- Admin CSS only in the admin area
- Admin JS only in the admin area
- Public assets only when necessary

---

## Compatibility

Test:

- Latest WordPress version
- Minimum supported PHP version
- Multisite

Never remove a public function without a migration path.

---

## Logging

Use:

error_log()

only in development.

For production:

- Use the plugin's logging system.
- No visible debug output for the end user.

---

## Testing

Before validation:

- Verify plugin activation.
- Verify deactivation.
- Verify uninstallation.
- Verify main features.

---

## When an agent modifies code

Always:

1. Read the complete file before modification.
2. Look for impacted calls.
3. Check existing WordPress hooks.
4. Preserve backward compatibility.

After modification:

- Provide a summary of changes.
- List potential risks.
- Identify tests to be performed.

---

## Prohibitions

Never:

- Modify `wp-config.php`
- Modify the WordPress Core
- Add a dependency without justification
- Disable security controls
- Rename existing public hooks

When in doubt:
prefer a minimal and safe modification.

## Expérience Utilisateur et Interface

Ne jamais utiliser les fenêtres pop-up natives du navigateur (alert(), confirm(), prompt()). Toujours utiliser des fenêtres modales HTML personnalisées, des messages intégrés (inline) ou les notifications natives de l'administration WordPress. Pensez également à toujours utiliser le système de traduction de WordPress (wp.i18n.__ en JS, __() et esc_html__() en PHP) pour l'intégralité des textes d'interface.
