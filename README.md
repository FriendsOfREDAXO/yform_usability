# YForm Usability für REDAXO 5 mit YForm ab Version 4

Mit diesem Addon wird die Bearbeitung von YForm-Datensätzen erleichtert.

## Features

- Yform Table Manager: Drag&Drop der Zeilen in den Datentabellen
(es muss ein Prio-Tabellen-Feld namens `prio` verwendet werden - zb. das schon in yform enhaltene `prio` Feld, in der Liste sichtbar)
- Yform Table Manager: schnelles Online/Offline schalten einzelner Zeilen
(es muss ein Tabellen-Feld namens `status` verwendet werden - zb. ein `choice`-Feld mit der Definition `Inaktiv=0,Aktiv=1,Pending=2,zu Erledigen=55`, in der Liste versteckt)
- Yform lang tabs: Ermöglicht die Gruppierung sprachabhängiger Felder in Tabs. 
- Yform Table Manager: Suche für Redakteure (ersetzt die YForm-Suchmaske, einschaltbar unter YForm → Usability)

> **Hinweis:** Damit alle Zeilen sortiert werden können, sollte man die Anzahl der Datensätze pro Seite auf eine möglichst hohe Zahl setzen. Das Sortieren per Drag&Drop funktioniert nur, wenn die Tabelle nach `prio` sortiert ist - die Standardsortierung der Tabelle sollte deswegen nach anlegen des `prio` Feldes nochmal eingestellt werden.

## Suche

Die Suchleiste ersetzt die YForm-Suchmaske in den Tabellen, für die sie unter *YForm → Usability* eingeschaltet ist.

- **Suchfeld:** Die Eingabe wird in Wörter zerlegt. Jedes Wort muss in mindestens einem durchsuchbaren Feld als Teiltreffer vorkommen. Kein `*` nötig, Groß-/Kleinschreibung egal, weiche Trennzeichen (`&shy;`) werden ignoriert, `"…"` hält eine Wortgruppe zusammen. Bei Auswahlfeldern zählt der angezeigte Text, bei Relationen der Anzeigetext des verknüpften Datensatzes, bei rein numerischen Wörtern auch die ID.
- **Zeitraum:** erscheint, wenn die Tabelle ein durchsuchbares Feld vom Typ `date`, `datetime` oder `datestamp` hat – oder ein Textfeld mit dem Attribut `{"type":"date"}` bzw. `{"type":"datetime-local"}` (Werte im Format `JJJJ-MM-TT`, andere Formate werden beim Datumsvergleich ignoriert). Vorgaben (Heute, Letzte 7/30 Tage, Dieser Monat, Dieses/Letztes Jahr) oder ein eigener Zeitraum, ein offenes Ende genügt, das Bis-Datum zählt mit.
- **+ Filter:** Feld → Bedingung → Wert. Die Bedingungen richten sich nach dem Feldtyp (enthält, ist genau, ist leer, zwischen, vor, nach, ist eine von …). Alle Filter werden mit UND verknüpft.
- **Aktive Filter** stehen als Chips über der Liste und lassen sich einzeln oder gemeinsam entfernen, dazu die Trefferzahl.
- **Gefilterte Spalten:** Ist ein Feld aus „+ Filter“ oder das Datumsfeld des Zeitraums in der Übersicht ausgeblendet, wird seine Spalte für die Dauer des Filters direkt hinter der ID eingeblendet (mit Filtersymbol, sortierbar).
- **Fundstellen** werden in der Liste gelb markiert: Wörter aus dem Suchfeld in allen Spalten, Werte von „enthält“-Filtern in ihrer Spalte (ab zwei Zeichen).
- Durchsuchbar ist, was im Table Manager als *durchsuchbar* markiert ist. Unter *Suche je Tabelle* lässt sich das Suchfeld auf einzelne Felder eingrenzen und bei mehreren Datumsfeldern das voreingestellte wählen.

Der Zustand steht in der URL und bleibt beim Blättern, Sortieren, Bearbeiten, Exportieren und in Relations-Popups erhalten:

| Parameter | Bedeutung |
|---|---|
| `yfu-q` | Suchfeld |
| `yfu-date[field\|preset\|from\|to]` | Zeitraum; `preset`: `today`, `last7`, `last30`, `this_month`, `this_year`, `last_year`, `custom` |
| `yfu-f[n][field\|op\|value]` | Filter; Felder und Bedingungen werden gegen die Tabelle geprüft, Unbekanntes wird ignoriert |

## Installation

* Addon über den Installer herunterladen oder
* alternativ GitHub-Version entpacken, den Ordner in `usability` umbenennen und in den REDAXO AddOn-Ordner legen `/redaxo/src/addons/yform_usabilty`
* alternativ über das AddOn zip_install hochladen und anschließend in der AddOns-Page installieren


## Lizenz, Autor, Credits

## Hilfe anfordern
Bitte besuche den REDAXO [Slack-Chat](https://www.redaxo.org/support/community/#slack)

## Fehler melden
Du hast einen Fehler gefunden oder wünscht dir ein Feature? Lege ein Issue auf Github an.


## Changelog

siehe `CHANGELOG.md` des AddOns

## Lizenz

MIT-Lizenz, siehe `LICENSE.md` des AddOns und Release Notes

### Autor

**Friends Of REDAXO**
[http://www.redaxo.org](http://www.redaxo.org)
[https://github.com/FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)

**Projekt-Lead**
[Alex Platter](https://github.com/lexplatt/)

## Credits

[Alex Platter](https://github.com/lexplatt/)
[Thomas Skerbis](https://github.com/skerbis)
[Ingo Winter](https://github.com/ingowinter)
[Alexander Walther](https://github.com/alxndr-w)
