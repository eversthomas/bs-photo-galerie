# BS Photo Galerie

Modulare PHP-Fotogalerie für öffentliche Präsentation und Admin-Verwaltung. Siehe auch die technische [**Verbesserungs-Roadmap**](docs/Verbesserungs-Roadmap.md).

---

## Haftungsausschluss

Die Nutzung dieser Software erfolgt **auf eigene Gefahr**. Es wird keine Gewähr für Vollständigkeit, Fehlerfreiheit, Verfügbarkeit oder Eignung für einen bestimmten Zweck übernommen. Betreiber sind selbst für Backups, Sicherheit, Rechtskonformität (z. B. Bildrechte, Datenschutz) und die Absicherung ihres Servers verantwortlich.

---

## Lizenz

Dieses Projekt steht unter der **GNU General Public License v3.0 oder später** (**GPL-3.0-or-later**), wie in `composer.json` angegeben.

- Volltext (Englisch): [GNU GPLv3](https://www.gnu.org/licenses/gpl-3.0.html)
- Die Lizenz verpflichtet u. a. dazu, bei Weitergabe den Quellcode offenzulegen und abgeleitete Werke unter derselben Lizenz zu stellen.

---

## Funktionen

### Öffentliche Website

- **Startseite** mit Vorschau der Galerie
- **Galerie** mit Masonry-Raster und **Lightbox** (Vollansicht)
- **Kategoriefilter** (öffentliche Kategorien für Gäste; private Kategorien nur nach Admin-Login sichtbar)
- **Sortierung**: manuell (wie im Backend) oder nach **Aufnahmedatum** (EXIF, sofern vorhanden)
- **Diashow** und optionale **Hintergrundmusik** (konfigurierbar in den Einstellungen)
- **Vorschaubilder** (`/thumb/…`) mit konsistenten Zugriffsregeln

### Administration (Backend)

- **Anmeldung** mit Session, optionaler Inaktivitäts-Abmeldung (`BSPHOTO_SESSION_IDLE_SECONDS` in `config/.env`)
- **Dashboard** und Abmeldung
- **Kategorien**: anlegen, bearbeiten, löschen, öffentlich/privat, Link-Hilfen für externe URLs
- **Medien**: Upload (mehrere Dateien), Liste mit Zeitraumfiltern (Stunde, Tage, Wochen, Monate, alle)
- **Reihenfolge** per Drag & Drop (Ansicht „Alle“)
- **Massenbearbeitung**: Kategorie zuweisen, **EXIF neu einlesen** für ausgewählte Bilder
- **Titel inline** speichern, **Medium bearbeiten** (Titel, Beschreibung, Kategorie, Sichtbarkeit)
- **Einzelnes EXIF neu einlesen** auf der Bearbeiten-Seite
- **Import** aus `public/import` (optional FTP, Bereinigung), CLI: `bin/import.php`
- **Einstellungen**: Seitentitel, Beschreibung, Galerie-/Musikoptionen, öffentliche Basis-URL, Content-Security-Policy (u. a. Report-Only), weitere Sicherheits- und Update-Schalter
- **Software-Update**: Abgleich mit GitHub; optional **Web-Update** (ZIP oder Git), sofern in `config/.env` freigeschaltet und Risiken akzeptiert werden

### Sicherheit und Technik

- **CSRF-Schutz** für POST-Anfragen im regulären Betrieb
- **Sicherheits-Header** (u. a. je nach Konfiguration)
- **Upload-Prüfung**: u. a. MIME/Erweiterungen, Inhaltsvalidierung (Intervention Image), optionale Scanner-Kette
- **PHP** ≥ 8.1, **PDO** (MySQL/MariaDB), **Composer**-Abhängigkeiten siehe `composer.json`

### Kommandozeile

| Skript | Zweck |
|--------|--------|
| `php bin/import.php` | Import aus Import-Ordner (optional `--ftp`, `--prune`, `--category=ID`) |
| `php bin/refresh-exif.php` | EXIF für gespeicherte Medien neu einlesen (optional `--limit=N`) |

---

## Installation

### Voraussetzungen

- PHP ≥ 8.1 mit Erweiterungen: `pdo_mysql`, `json`, `mbstring`; empfohlen: `gd`, `fileinfo`, `exif`
- MySQL oder MariaDB
- Composer (für die Erstinstallation und manuelle Updates mit `composer install`)
- Webserver mit **DocumentRoot auf das Verzeichnis `public/`**

### Schritte

1. Projekt auschecken oder entpacken.
2. Im Projektroot: `composer install --no-dev` (ohne Dev-Abhängigkeiten, falls keine definiert sind – entspricht meist `composer install`).
3. Webserver so konfigurieren, dass nur `public/` ausgeliefert wird; `config/`, `storage/`, `vendor/` dürfen nicht öffentlich listbar sein (siehe Hinweise in `config/.env.example`).
4. Optional: `config/.env.example` nach `config/.env` kopieren und anpassen (nicht ins öffentliche Web legen).
5. Browser öffnen: wird `storage/locks/install.lock` noch nicht gefunden, leitet die Anwendung auf **`/install/`** weiter.
6. Im **Webbasierten Installer** Datenbankverbindung prüfen und Installation ausführen; danach ersten Admin-Benutzer anlegen bzw. die Anzeige des abgeschlossenen Setups beachten.

Nach erfolgreicher Installation die Zugriffsrechte auf `storage/` prüfen (Schreibbarkeit für Logs, Locks, Uploads, Caches, je nach Konfiguration).

---

## Update

### Über die Verwaltung (optional)

Wenn in `config/.env` **`BSPHOTO_ALLOW_WEB_UPDATE=1`** oder **`BSPHOTO_ALLOW_GIT_UPDATE=1`** gesetzt ist, kann unter **Administration → Software-Update** ein Update von GitHub angestoßen werden (ZIP ohne `.git` oder Git-Checkout, je nach Deployment). Vorher **Backup** (Dateien + Datenbank) erstellen; Composer kann auf dem Server fehlen – der ZIP-Pfad ist dafür ausgelegt, optionalen Composer-Lauf anders zu behandeln als den Git-Pfad (siehe Hinweise in der Update-Oberfläche).

### Manuell

1. Backup von Dateien (Datenbank, hochgeladene Medien unter `public/…`, `config/config.php`, `config/.env`).
2. Neue Version einspielen (z. B. `git pull` oder Dateien ersetzen).
3. Im Projektroot: `composer install` (empfohlen, damit `vendor/` zur Version passt).
4. Bei Bedarf Browser: Admin-Bereich öffnen; bei Schema-Hinweisen die mitgelieferten Migrationen/Patches laut Release-Notes ausführen (falls vorhanden).

Die veröffentlichte Versionsnummer steht in der Datei **`VERSION`** im Projektroot.

---

## Dokumentation

- [Verbesserungs-Roadmap & IST-Analyse](docs/Verbesserungs-Roadmap.md)
