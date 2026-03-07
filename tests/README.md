# Pinnwand Unit-Tests

## Voraussetzungen
- PHP CLI installiert (`php -v`)
- PHPUnit installiert (z. B. lokal via Composer)
- WordPress Test Suite installiert (`wordpress-tests-lib`)

## Schnellstart
1. (Optional) PHPUnit lokal installieren:
   `composer require --dev phpunit/phpunit`
2. WP Test Suite Pfad setzen (falls nicht `/tmp/wordpress-tests-lib`):
   `export WP_TESTS_DIR=/pfad/zu/wordpress-tests-lib`
3. Tests starten:
   `vendor/bin/phpunit -c /Users/hildebrandb/dev/pinnwand-codex/pinnwand/tests/phpunit.xml.dist`

## Abgedeckte MVP-Kernfälle
- Registrierung von `pw_artikel`, `pw_kategorie`, `pw_tag`
- Shortcodes `pw_*`
- Suchformular (nur Suchfeld + Checkbox)
- Suche: Standard ohne ausgeliehene Artikel, optional mit ausgeliehenen Artikeln
- Keyword-Suche über Tags
- Ownership-Rechteprüfung (`PW_Security::can_edit_article`)
- Rate-Limit Blockade bei überschrittenem Limit
- Login-Pflicht im Profil-Shortcode
- Keyword-Vorschläge im Artikel-Formular
