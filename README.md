yform_usability
================

Mit diesem Addon wird die Bearbeitung von YForm-Datensätzen erleichtert.

Features
-------

- Yform Table Manager: Drag&Drop der Zeilen in den Datentabellen
(es muss ein Prio-Tabellen-Feld namens `prio` verwendet werden - zb. das schon in yform enhaltene `prio` Feld, in der Liste sichtbar)
- Yform Table Manager: schnelles Online/Offline schalten einzelner Zeilen
(es muss ein Tabellen-Feld namens `status` verwendet werden - zb. ein `select` mit der Definition `online=1,offline=0`, in der Liste versteckt)

> **Hinweis:** Damit alle Zeilen sortiert werden können, sollte man die Anzahl der Datensätze pro Seite auf eine möglichst hohe Zahl setzen. Das Sortieren per Drag&Drop funktioniert nur, wenn die Tabelle nach `prio` sortiert ist - die Standardsortierung der Tabelle sollte deswegen nach anlegen des `prio` Feldes nochmal eingestellt werden.

Installation
-------

* Addon über den Installer herunterladen oder
* alternativ GitHub-Version entpacken, den Ordner in `usability` umbenennen und in den REDAXO AddOn-Ordner legen `/redaxo/src/addons/yform_usabilty`
* alternativ über das AddOn zip_install hochladen und anschließend in der AddOns-Page installieren
