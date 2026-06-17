=== Pinnwand ===
Contributors: tellysava-rgb
Tags: pinnwand, inserate, verleih, verkauf, community
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 1.1.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Digitale Pinnwand fuer Haeuser und Quartiere — Artikel verleihen oder verkaufen, nur fuer eingeloggte Mitglieder.

== Description ==

**Pinnwand** verwandelt jede WordPress-Seite in eine digitale Schwarzwand fuer eine geschlossene Gemeinschaft — ideal fuer ein Wohnhaus, eine Siedlung oder ein Quartier. Mitglieder koennen Artikel inserieren, die sie verleihen oder verkaufen moechten. Die Kontaktangaben der Inserenten sind nur fuer eingeloggte Benutzer sichtbar.

**Inseratetypen**

Jeder Inseratetyp (z.B. Verleih, Verkauf, Verschenken) kann individuell konfiguriert werden. Pro Typ laesst sich festlegen, ob ein Verleih-Status ("Verfuegbar" / "Ausgeliehen") angezeigt werden soll. Fuer jeden Inseratetyp kann eine eigene WordPress-Seite erstellt werden oder alle Typen gemeinsam auf einer Seite erscheinen.

**Kategorien und Keywords**

Inserate koennen mit eigenen Kategorien und Keywords versehen werden. Die Suchmaske erlaubt das Filtern nach Kategorie, Inseratetyp und Suchbegriff. Kategorien und Keywords sind als anklickbare Chips direkt auf der Pinnwand sichtbar.

**Datenschutz und Zugriffsschutz**

Die Kontaktangaben (Name, E-Mail, Telefon, Adresse) eines Inserenten sind ausschliesslich fuer eingeloggte Mitglieder einsehbar. Die Registrierung ist durch einen Einladungscode geschuetzt — nur Personen mit dem Code koennen sich anmelden.

**Weitere Funktionen**

* Bilder pro Inserat hochladen (Anzahl und Groesse konfigurierbar)
* Benutzer-Dashboard mit eigenen Inseraten (bearbeiten, loeschen, sperren)
* Profilverwaltung mit Datenexport (CSV) und Kontoloesch-Funktion
* Admin-Uebersicht aller Inserate mit Benutzerinformationen
* Optionaler CAPTCHA-Schutz (Cloudflare Turnstile) bei Registrierung
* Automatische Plugin-Updates ueber GitHub-Releases

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

= 1.1.1 =
* Icons "Als ausgeliehen markieren" und "Als verfuegbar markieren" geaendert (remove/insert)
* Dashboard-Tabelle bricht via negative Margins aus der Theme-Content-Breite aus (responsiv, zentriert)
* Alle Plugin-Formularelemente einheitlich auf font-size 1rem gesetzt
* Tote CSS-Klassen entfernt

= 1.1.0 =
* Kategorien im Bearbeiten-Formular hierarchisch verschachtelt anzeigen
* Keywords-Eingabefeld: Label und Input auf gleicher Hoehe, Placeholder mit Beispiel ergaenzt
* Stern-Icons: gefuellter Stern fuer Favorit-Bild, leerer Stern fuer andere Bilder
* Meine Inserate: Verleih-Badge nur noch bei Verleih-Inseratetypen sichtbar
* Profil: Benutzername als Readonly-Feld in erster Zeile hinzugefuegt
* Profil: Button "Profil loeschen" auf der rechten Seite des Formulars

= 1.0.2 =
* Projektregeln und Git-Workflow in CLAUDE.md dokumentiert
* Versionierungsschema und Release-Prozess festgelegt

= 1.0.1 =
* Aktualisierte Plugin-Beschreibung mit vollstaendiger Funktionsuebersicht

= 1.0.0 =
* Erster stabiler Release

= 0.6.0 =
* Neu: Checkbox "Verleih möglich" pro Inseratetyp — Status-Feld nur bei Verleih-Typen sichtbar
* Aenderung: "Status" umbenannt in "Verleih" ueberall (Einzelansicht, Editierformular, Dashboard, Admin)
* Aenderung: "Sichtbarkeit" umbenannt in "Anzeige"
* Aenderung: Aktionen-Buttons als Icons (Dashboard & Admin)
* Aenderung: Editierformular — Label und Eingabefeld auf gleicher Hoehe
* Neu: Eigene Pinnwand-Kategorien (pw_kategorie) getrennt von WordPress-Kategorien
* Neu: Keywords-Taxonomie (pw_tag)

= 0.5.0 =
* Neu: Admin kann Inserate in "Alle Inserate" direkt loeschen
* Neu: Inseratetyp-Key editierbar solange keine Inserate mit diesem Key existieren; Key ist gesperrt sobald Inserate vorhanden sind
* Aenderung: Spalte "Bezeichnung aendern" in "Bearbeiten" umbenannt (zeigt jetzt auch Key-Feld fuer leere Typen)

= 0.4.2 =
* Neu: Inseratetypen sind jetzt dynamisch konfigurierbar (Pinnwand > Inseratetypen)
* Neu: Admin-Seite "Inseratetypen" mit Shortcode-Referenz (dynamisch nach aktiven Typen)
* Neu: Eigene Inseratetypen koennen hinzugefuegt und — sofern keine Inserate damit verknuepft sind — wieder geloescht werden
* Aenderung: Shortcode-Referenz von der Einstellungsseite zur Inseratetypen-Seite verschoben

= 0.4.1 =
* Neu: Filter-Leiste in "Alle Inserate" — Inseratetyp, Titel-Suche, Kategorie, Status, Benutzername, Rolle

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

= 0.5.0 =
Admin kann Inserate jetzt direkt aus der Uebersicht loeschen. Inseratetyp-Keys koennen bearbeitet werden, solange noch keine Inserate damit verknuepft sind.

= 0.4.2 =
Die Inseratetypen sind jetzt dynamisch. Bestehende Inserate mit "verleih" und "verkauf" funktionieren unveraendert weiter.

= 0.4.1 =
Neue Filter-Leiste in der Admin-Uebersicht "Alle Inserate". Kein Eingriff in bestehende Daten.

= 0.4.0 =
Neue Funktion: Suchseiten koennen jetzt auf einen Inseratetyp eingeschraenkt werden. Bestehende `[pw_search_form]`-Shortcodes funktionieren unveraendert weiter.
