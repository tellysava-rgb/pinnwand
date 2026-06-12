=== Pinnwand ===
Contributors: tellysava-rgb
Tags: pinnwand, inserate, verleih, verkauf, community
Requires at least: 6.3
Tested up to: 6.8
Stable tag: 0.4.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Eine digitale Pinnwand fuer Gemeinschaften zum Verleihen und Verkaufen von Artikeln.

== Description ==

Pinnwand ist ein WordPress-Plugin fuer geschlossene Gemeinschaften. Mitglieder koennen Artikel zum Verleihen oder Verkaufen inserieren. Die Registrierung ist per Einladungscode geschuetzt.

**Funktionen:**

* Inserate erstellen, bearbeiten und loeschen (Verleih & Verkauf)
* Bilder pro Inserat hochladen (konfigurierbar)
* Suchmaske mit Filter nach Inseratetyp und Status
* Dedizierte Seiten fuer Verleih- oder Verkaufsinserate moeglich
* Benutzer-Dashboard mit eigenen Inseraten
* Profilverwaltung mit Datenexport (CSV) und Kontoloesch-Funktion
* Registrierung per Einladungscode mit optionalem Ablaufdatum
* Optionaler CAPTCHA-Schutz (Cloudflare Turnstile) bei Registrierung
* Admin-Uebersicht aller Inserate mit Benutzerinformationen
* Automatische Updates ueber GitHub-Releases
* Rate-Limiting gegen Missbrauch bei der Inseratserstellung

== Installation ==

1. Plugin-ZIP herunterladen (aktuellste Version unter Releases auf GitHub).
2. Im WordPress-Backend unter **Plugins > Installieren > Plugin hochladen** die ZIP-Datei hochladen.
3. Plugin aktivieren.
4. Unter **Pinnwand > Einstellungen** den Einladungscode und weitere Parameter konfigurieren.
5. WordPress-Seiten anlegen und die gewuenschten Shortcodes einfuegen (siehe Shortcode-Referenz).

== Shortcodes ==

`[pw_search_form]`
Zeigt alle Inserate (Verleih + Verkauf) mit Suchmaske.

`[pw_search_form offer_type="verleih"]`
Zeigt nur Verleih-Inserate. Der Inseratetyp-Filter ist ausgeblendet, die Option "Ausgeliehene anzeigen" ist verfuegbar.

`[pw_search_form offer_type="verkauf"]`
Zeigt nur Verkaufs-Inserate. Inseratetyp-Filter und "Ausgeliehene anzeigen" sind ausgeblendet.

`[pw_article_form]`
Formular zum Erfassen und Bearbeiten von Artikeln. Nur fuer eingeloggte Benutzer mit Berechtigung.

`[pw_user_dashboard]`
Uebersicht der eigenen Inserate mit Bearbeiten- und Loeschen-Funktion.

`[pw_profile_form]`
Profilformular mit Datenexport und Moeglichkeit zur Kontoloesch-Anfrage.

== Frequently Asked Questions ==

= Wie schuetze ich die Registrierung? =
Unter **Pinnwand > Einstellungen** einen Einladungscode und ein Gueltigkeitsdatum setzen. Nur Personen mit dem Code koennen sich registrieren. Optional kann Cloudflare Turnstile als CAPTCHA aktiviert werden.

= Wie erstelle ich eine Seite nur fuer Verleih-Inserate? =
Eine neue WordPress-Seite anlegen und den Shortcode `[pw_search_form offer_type="verleih"]` einfuegen.

= Wie funktionieren automatische Updates? =
Das Plugin prueft automatisch auf neue Versionen im GitHub-Repository. Steht ein Update bereit, erscheint die gewohnte WordPress-Update-Meldung im Backend.

= Welche Bildformate sind erlaubt? =
Standardmaessig JPG, PNG und WebP. Die erlaubten Formate sowie Groessenlimits koennen in den Einstellungen angepasst werden.

== Screenshots ==

1. Suchmaske mit Ergebnisliste (Kartenansicht)
2. Artikel-Erfassungsformular mit Bildupload
3. Benutzer-Dashboard mit eigenen Inseraten
4. Profilformular
5. Admin-Einstellungsseite mit Shortcode-Referenz
6. Admin-Uebersicht aller Inserate

== Changelog ==

= 0.4.0 =
* Neu: `offer_type`-Parameter fuer `[pw_search_form]` — dedizierte Verleih- oder Verkaufsseiten moeglich
* Neu: Admin-Uebersicht aller Inserate mit Benutzerinformationen (Pinnwand > Alle Inserate)
* Neu: Shortcode-Referenz auf der Einstellungsseite
* Neu: Automatische Updates ueber GitHub-Releases (plugin-update-checker)
* Neu: GitHub Actions Workflow fuer automatischen Release-Build
* Fix: Nonce-Pruefung fuer AJAX-Tag-Vorschlaege ergaenzt
* Fix: Inline-JavaScript in externe Datei ausgelagert
* Fix: `is_wp_error()`-Pruefung im CSV-Export konsistenziert
* Fix: ABSPATH-Check in uninstall.php ergaenzt

= 0.3.1 =
* Stabilitaetsverbesserungen und kleinere Korrekturen

= 0.3.0 =
* Bilder-Galerie pro Inserat mit Hauptbild-Auswahl
* Rate-Limiting fuer Inseratserstellung
* Cloudflare Turnstile CAPTCHA bei Registrierung
* Verbesserte Sicherheit (Nonces, Capability-Checks)

= 0.2.0 =
* Profilformular mit Adressdaten
* CSV-Datenexport fuer Benutzer
* Profil-Loeschfunktion

= 0.1.0 =
* Erstveroeffentlichung

== Upgrade Notice ==

= 0.4.0 =
Neue Funktion: Suchseiten koennen jetzt auf einen Inseratetyp eingeschraenkt werden. Bestehende `[pw_search_form]`-Shortcodes funktionieren unveraendert weiter.
