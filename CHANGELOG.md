# Changelog

## **xx.xx.20xx x.x.x**

- Neue Suche statt der Inline-Suche: ein Suchfeld ohne `*` (Wörter UND-verknüpft, Teiltreffer über alle durchsuchbaren Felder), Zeitraum direkt in der Suchzeile, „+ Filter“ mit Bedingungen je Feldtyp, aktive Filter als Chips mit Trefferzahl. Gefilterte, sonst ausgeblendete Spalten werden vorübergehend eingeblendet, Fundstellen in der Liste markiert. Einstellungen je Tabelle: Felder für das Suchfeld, voreingestelltes Datumsfeld. Siehe README, Abschnitt „Suche“.
- Suche: behoben `( AND (`-SQL-Fehler bei vorhandenen Vorfiltern (#156), Suche auf eingebetteten Manager-Seiten verlässt die Seite bzw. ersetzt die Tabs nicht mehr (#187), Vorfilter (`rex_yform_filter`), Sortierung und Popup-Zustand bleiben erhalten, Mehrfach-Relationen und Mehrfach-Choice werden korrekt gefunden, „leer“ berücksichtigt `NULL`, `%` und `_` werden wörtlich gesucht, Felder aus der URL werden gegen die durchsuchbaren Felder geprüft, kein doppelter `pjax:end`-Handler mehr, Bedienelemente mit Labels und per Tastatur erreichbar.
- Suche: die URL-Parameter `yfu-term`, `yfu-searchfield` und `yfu-action=search` entfallen; neu sind `yfu-q`, `yfu-date[…]` und `yfu-f[…]`.
- Inline-Suche: die JS-Funktionen `YformUsability.doYformSearch()` und `resetYformSearch()` entfallen.
- PHP-Anforderung auf `>=8.1` angehoben (das Addon nutzte schon PHP-8.1-Syntax).
- API (`rex_api_yform_usability_api`): Sortier- und Filterparameter werden strenger geprüft, Filterbedingungen sind signiert (`filter_sig` in der Sortier-URL). Status und Sortierung berücksichtigen die Tabellenrechte, Feldsortierung nur für Admins, das CSRF-Token wird geprüft. `data-table`/`data-filter` werden escaped.
- Interne Optimierung (Callbacks IDE- und RexStan-freundlich in der "First class callable syntax" notiert).
- Initiales Changelog
- Überprüfung der Kompatibilität zu YForm 5
- PHP-8.x-Deprecations behoben: NULL-Übergaben an `trim()`, `explode()`, `strlen()`, `unserialize()`, `json_decode()`, `strtotime()` (u.a. `Utils::getStatusColumnParams()`), implizit nullable Parameter in `Model` (PHP 8.4), NULL als Array-Offset (PHP 8.5).
- Veraltete Core-Aufrufe ersetzt: `rex_string::versionCompare()` → `rex_version::compare()`, `rex_list`-Spaltenformat `strftime` → `date`.
- YForm 5: entfernte Klasse `rex_yform_value_select` in `Utils` durch `Extensions::getArrayFromString()` ersetzt; `Sprog\Wildcard` statt globalem Alias.
- Thumbnail-Spalte: TypeError bei leerem Medienfeld (NULL) behoben; `Model::insertUpdate()` rief nicht existierendes `setId()` auf.
- Inline-Suche: Suchbegriff und Feld-Labels im Fragment escaped.
