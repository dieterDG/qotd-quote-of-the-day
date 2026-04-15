=== QOTD – Zitat des Tages ===
Contributors: dietergeiling
Tags: quote, zitat, shortcode, rest-api
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt täglich ein wechselndes Zitat auf der Website an – cache-sicher über die WordPress REST API.

== Description ==

QOTD (Zitat des Tages) fügt einen eigenen Beitragstyp für Zitate hinzu und stellt täglich automatisch eines davon auf der Website dar.

Das angezeigte Zitat wechselt einmal pro Tag und wird deterministisch anhand des aktuellen Datums ausgewählt – ohne Zufallselement, sodass alle Besucher am selben Tag dasselbe Zitat sehen. Die Ausgabe erfolgt cache-sicher über einen REST-API-Endpunkt, der mit passenden HTTP-Caching-Headern ausgeliefert wird.

**Funktionen:**

* Eigener Beitragstyp „Zitate" im WordPress-Adminbereich
* Felder für Zitattext, Autor und optionalen Zusatz (z. B. Quelle oder Jahr)
* Tägliches Wechseln des Zitats – konsistent für alle Besucher
* Einbindung per Shortcode `[qotd]`
* Ausgabe über REST-API-Endpunkt (`/wp-json/qotd/v1/today`)
* Ausschließlich PlainText – kein HTML in Zitaten gespeichert oder ausgegeben
* Automatischer Fallback-Titel wenn kein Titel gesetzt wird

== Installation ==

1. Den Ordner `qotd` in das Verzeichnis `/wp-content/plugins/` hochladen.
2. Das Plugin im WordPress-Adminbereich unter „Plugins" aktivieren.
3. Unter dem neuen Menüpunkt „Zitate" beliebig viele Zitate anlegen und veröffentlichen.
4. Den Shortcode `[qotd]` an der gewünschten Stelle in einer Seite oder einem Widget einfügen.

Der Shortcode akzeptiert den optionalen Parameter `class` für eine zusätzliche CSS-Klasse:

`[qotd class="mein-stil"]`

Das Styling der Ausgabe (`.qotd`, `.qotd__text`, `.qotd__author`, `.qotd__source`) erfolgt vollständig über das eigene Theme.

== Changelog ==

= 1.1.0 =
* Erstes öffentliches Release
* Custom Post Type `qotd_quote` mit PlainText-Metafeldern (Text, Autor, Zusatz)
* Deterministische Tagesauswahl per `crc32(Datum + Site-URL)`
* REST-API-Endpunkt `/wp-json/qotd/v1/today` mit HTTP-Caching-Headern
* Shortcode `[qotd]` mit optionalem `class`-Parameter
* Automatische Titelgenerierung aus dem Zitattext
* Transient-Cache für die Liste veröffentlichter Zitat-IDs
* Uninstall-Routine entfernt alle Plugin-Daten rückstandslos
