# YForm Usability für REDAXO 5 mit YForm ab Version 4

Mit diesem Addon wird die Bearbeitung von YForm-Datensätzen erleichtert.

## Features

- Yform Table Manager: Drag&Drop der Zeilen in den Datentabellen
(es muss ein Prio-Tabellen-Feld namens `prio` verwendet werden - zb. das schon in yform enhaltene `prio` Feld, in der Liste sichtbar)
- Yform Table Manager: schnelles Online/Offline schalten einzelner Zeilen
(es muss ein Tabellen-Feld namens `status` verwendet werden - zb. ein `choice`-Feld mit der Definition `Inaktiv=0,Aktiv=1,Pending=2,zu Erledigen=55`, in der Liste versteckt)
- Yform lang tabs: Ermöglicht die Gruppierung sprachabhängiger Felder in Tabs. 

> **Hinweis:** Damit alle Zeilen sortiert werden können, sollte man die Anzahl der Datensätze pro Seite auf eine möglichst hohe Zahl setzen. Das Sortieren per Drag&Drop funktioniert nur, wenn die Tabelle nach `prio` sortiert ist - die Standardsortierung der Tabelle sollte deswegen nach anlegen des `prio` Feldes nochmal eingestellt werden.

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
