# Changelog

## **xx.xx.20xx x.x.x**

- Interne Optimierung (Callbacks IDE- und RexStan-freundlich in der "First class callable syntax" notiert).
- Initiales Changelog
- Überprüfung der Kompatibilität zu YForm 5
- PHP-8.x-Deprecations behoben: NULL-Übergaben an `trim()`, `explode()`, `strlen()`, `unserialize()`, `json_decode()`, `strtotime()` (u.a. `Utils::getStatusColumnParams()`), implizit nullable Parameter in `Model` (PHP 8.4), NULL als Array-Offset (PHP 8.5).
- Veraltete Core-Aufrufe ersetzt: `rex_string::versionCompare()` → `rex_version::compare()`, `rex_list`-Spaltenformat `strftime` → `date`.
- YForm 5: entfernte Klasse `rex_yform_value_select` in `Utils` durch `Extensions::getArrayFromString()` ersetzt; `Sprog\Wildcard` statt globalem Alias.
- Thumbnail-Spalte: TypeError bei leerem Medienfeld (NULL) behoben; `Model::insertUpdate()` rief nicht existierendes `setId()` auf.
- Inline-Suche: Suchbegriff und Feld-Labels im Fragment escaped.
