# Photo Gallery System – Projektplan

## Ziel
Modulare, schlanke Open-Source Fotogalerie mit Backend, automatischem Import und hoher Erweiterbarkeit.

Lizenz: GPL  
Architektur: Modular, Framework-unabhängig  

---

# Phase 0 – Setup & Architektur

## Ziele
- Grundstruktur erstellen
- Core-System definieren

## Aufgaben
- Projektstruktur anlegen:
/app
/public
/config
/templates
/storage
/docs

- Composer initialisieren
- Libraries installieren:
  - FastRoute
  - phpdotenv
  - Intervention Image

- Basis-Konfiguration (.env)

---

# Phase 1 – Installer (WordPress-like)

## Ziele
- Vollautomatische Installation

## Features
- DB-Verbindung testen
- Tabellen erstellen
- Admin-User anlegen
- config.php generieren
- Install-Lock setzen

## Bonus
- "Dev Reset Mode":
  - Installer kann zurückgesetzt werden
  - DB optional neu initialisieren

---

# Phase 2 – Core-System

## Ziele
- stabile Grundlage

## Module
- Router
- Controller-System
- DB-Wrapper (PDO)
- Config Loader
- Auth System

---

# Phase 3 – Medien-System

## Ziele
- Upload + Verarbeitung

## Features
- Multi-Upload
- MIME-Check
- Hashing
- EXIF lesen
- Thumbnail generieren

---

# Phase 4 – Importer (FTP-Scan)

## Ziele
- automatischer Bildimport

## Features
- Ordner scannen
- neue Bilder erkennen
- gelöschte Bilder erkennen
- DB synchronisieren

## Bonus
- CLI Import Script

---

# Phase 5 – Backend UI

## Ziele
- Admin Oberfläche

## Seiten
- Login
- Dashboard
- Bilderverwaltung
- Upload
- Kategorien

## Features
- Inline Editing
- Drag & Drop Sortierung

---

# Phase 6 – Galerie Frontend

## Ziele
- Darstellung

## Features
- Grid Layout
- Lightbox
- Lazy Loading
- Mobile Support

---

# Phase 7 – Erweiterte Features

## Slideshow
- Autoplay
- Timer
- Fullscreen

## Musik
- Playlist
- Steuerung

---

# Phase 8 – Layout-System

## Ziele
- konfigurierbare Darstellung

## Features
- Templates
- CSS Variablen
- Layout-Auswahl im Backend

---

# Phase 9 – Sicherheit

## Maßnahmen
- CSRF Schutz
- Passwort Hashing
- Upload Validierung
- .htaccess Schutz
- Prepared Statements

---

# Phase 10 – Performance

## Maßnahmen
- Thumbnail Cache
- Lazy Loading
- Query Optimierung

---

# Phase 11 – Plugin-System (optional)

## Ziele
- Erweiterbarkeit

## Features
- Hooks
- Plugin Loader

---

# Entwicklungsprinzipien

- Keine unnötigen Abhängigkeiten
- Saubere Trennung von Logik und Darstellung
- API-first Ansatz
- Sicherheit von Anfang an
- Erweiterbarkeit priorisieren

---

# MVP Definition

Minimal funktionsfähig:
- Installer
- Upload
- Importer
- Galerie Anzeige
- Admin Login