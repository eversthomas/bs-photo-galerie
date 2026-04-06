# Verbesserungs-Roadmap – BS Photo Galerie

*Erstellt auf Grundlage des IST-Codes im Repository (evidenzbasiert). Kein Refactoring in der Analysephase; diese Datei dient der schrittweisen Planung.*

---

## 1. Executive Summary

**Architektur:** Das Projekt ist ein schlankes Custom-PHP-System (PHP ≥ 8.1, FastRoute, PDO, PSR-4 unter `app/`). Die zentrale Klasse `BSPhotoGalerie\Core\Application` verbindet Konfiguration, Request, Session-Start, Routing, **Service Locator**-artige Lazy-Factory-Methoden für Datenbank, Repositories und Dienste sowie CSRF- und Auth-Policy direkt in `run()`. Controller werden per `new $class($this)` instanziiert und erhalten so die gesamte `Application`. Es gibt **keine** echte Layer-Trennung zwischen „Infrastructure“ und „HTTP“; die **Business-Logik** liegt überwiegend in Services (`MediaUploadService`, Update-Services) und teils direkt in Controllern (Validierung, Redirects, Flash).

**Wartbarkeit:** Für die Projektgröße ist die Struktur nachvollziehbar (Namespaces, Repositories, einige Services). Wachstum erschwert jedoch: jede neue Abhängigkeit verlängert die `Application`, und Tests ohne die volle App sind aufwendig. `Database::transaction()` existiert, wird im Projekt **nirgends** genutzt (`grep` über `->transaction(`: keine Treffer) – mehrstufige DB-Operationen laufen ohne explizite Transaktionen auf Repository-Ebene.

**Sicherheit:** Positiv: CSRF für alle POSTs in `Application::run()` (`CsrfMiddleware`), Session-Cookies mit `httponly`, `samesite=Lax`, `secure` bei HTTPS, `password_verify` in `AuthService`, PDO nur mit Prepared Statements (`Database`), Upload-Pipeline mit `is_uploaded_file`, MIME-Prüfung, Intervention Image als Inhalts-Check, Whitelist für MIME/Erweiterungen, `SecurityHeaders` (u. a. `X-Content-Type-Options`, `X-Frame-Options`). Lücken/Verbesserungspotenzial: kein zentrales Logging bei Fehlern, generische 500-Antwort ohne Diagnose; Sicherheits-Header ohne CSP; **Git-Update** meldet bei fehlgeschlagenem `composer install` **Gesamtfehler**, obwohl `git` bereits ausgeführt wurde (siehe Kernproblem 5 und Abschnitt 10).

---

## 2. Architekturelle Kernprobleme

| # | Problem | Konkrete Code-Referenz | Kurzfolge |
|---|--------|------------------------|-----------|
| 1 | **Zentrale „God Object“-Tendenz der `Application`** | `app/Core/Application.php`: Docblock „DI-Light“ (Zeile 40–42), zahlreiche Getter-Factories (`database()`, `users()`, `mediaRepository()`, `mediaUploadService()`, … Zeilen 95–196), `router()` mit allen Routen (351–392), `run()` mit Session, CSRF, Auth (244–274) | Hohe Kopplung aller Features an eine Klasse; erschwert isoliertes Testen und schrittweise Erweiterung. |
| 2 | **Kein Container / keine Constructor-Injection für Controller** | `Application::invoke()` instanziiert `new $class($this)` (Zeilen 280–288); `BaseController` hält `Application $app` ( `app/Controllers/BaseController.php` Zeilen 15–17) | Versteckte Abhängigkeit „jede Methode kann alles aus `$app` ziehen“; keine explizite Abhängigkeitsliste pro Controller. |
| 3 | **Auth-Policy per String-Prefix auf Controller-Namespace** | `Application::applyAuthPolicy()` (Zeilen 294–308): `LoginController` Sonderfall; sonst `str_starts_with($class, '...\Admin\\')` → `AuthMiddleware` | Funktional, aber fragil bei Umbenennung/Namespace; keine deklarative Route-Metadaten (z. B. `['auth' => true]`). |
| 4 | **CSRF global für alle POSTs ohne Ausnahmeliste in der Route** | `Application::run()` Zeilen 267–268: bei jedem POST `CsrfMiddleware->validatePost()` vor Auth | Konsistent und sicher; Ausnahmen (z. B. Webhooks) wären ohne Refactoring schwer – derzeit irrelevant, aber architektonisch starr. |
| 5 | **Git-Update: Erfolg auf Dateisystem vs. Fehlermeldung in der UI** | `GitApplicationUpdater::run()`: nach erfolgreichem Git-Checkout/Fetch wird `composer install` ausgeführt; bei **`!$composer['ok']` wird `ok: false` zurückgegeben** (Zeilen 128–132), obwohl der **Code bereits per Git geändert** sein kann | Genau das beschriebene Nutzerphänomen: „Fehlermeldung, aber Update scheint zu greifen“ (VERSION/Code neu, Composer auf dem Server fehlt oder Exit-Code ≠ 0). **ZIP-Pfad** behandelt Composer optional (`ZipReleaseUpdater::runComposerOptional`, `app/Services/Update/ZipReleaseUpdater.php` Zeilen 121–137) und bricht den Gesamtstatus nicht ab. |
| 6 | **Transaktionen angeboten, aber ungenutzt** | `Database::transaction()` (`app/Services/Database.php` Zeilen 111–123); keine Verwendung im Projekt | Risiko inkonsistenter Zustände bei zusammenhängenden Writes (z. B. Upload: `insert` + Thumbnail – hier gibt es manuelles `deleteById` bei Thumbnail-Fehler in `MediaUploadService`, aber kein übergreifendes DB-Transaktionskonzept). |
| 7 | **Fehlerbehandlung / Logging minimal** | `public/index.php` Zeilen 33–37: `catch (Throwable)` → `Application::handleException()`; `Application::handleException()` (Zeilen 398–403) setzt nur HTTP 500 und statischen Text **ohne Log** | Produktionsdebugging und Audit schwierig; keine Korrelation mit Updater-Logs. |
| 8 | **Flash nur eine Nachricht** | `Flash::set()` überschreibt `_flash_message` (`app/Core/Flash.php` Zeilen 14–16) | Bei mehreren gleichzeitigen Hinweisen (z. B. Teil-Erfolg Upload) wird nur ein aggregierter Text genutzt; für komplexe UX limitierend. |

---

## 3. Priorisierte Maßnahmen

### Phase 1 – Quick Wins (geringes Risiko, hoher Nutzen)

#### 1.1 Git-Update: Composer-Ergebnis von „Gesamtfehler“ trennen
- **Beschreibung:** Analog zum ZIP-Updater soll ein fehlgeschlagener `composer install` nach erfolgreichem Git-Schritt **nicht** mehr `ok: false` für den gesamten Vorgang erzwingen, sondern als **Warnung/Hinweis** in `log` landen und `ok: true` mit klarer Admin-Meldung (oder separates Flag `composer_ok` in der Response-Struktur).
- **Betroffene Dateien/Klassen:** `app/Services/Update/GitApplicationUpdater.php` (Zeilen 128–132), `app/Controllers/Admin/UpdateController.php` (Flash-Text bei Erfolg/Warnung, Zeilen 162–173).
- **Zielbild:** Administratoren sehen **„Update durchgeführt; Composer bitte manuell / vendor hochladen“** statt irreführendem „Update fehlgeschlagen“ bei bereits gepulltem Code.
- **Umsetzungsschritte:** (1) Semantik `run()` definieren (z. B. `steps: { git: ok, composer: ok }`). (2) Nach erfolgreichem Git bei Composer-Fehler `ok: true` + Hinweise in `log`. (3) `UpdateController` Flash anpassen (Success + optionale Warnbox). (4) Manuell testen: ohne `composer` im PATH.
- **Risiko:** low  
- **Impact:** high

#### 1.2 Veraltete Fehlermeldung in Git-Updater an `WebUpdatePolicy` anpassen
- **Beschreibung:** `GitApplicationUpdater::run()` meldet bei deaktiviertem Update noch `BSPHOTO_ALLOW_GIT_UPDATE` (`app/Services/Update/GitApplicationUpdater.php` Zeilen 52–54), während `WebUpdatePolicy::isWebUpdateAllowed()` mehrere Variablen prüft (`BSPHOTO_ALLOW_WEB_UPDATE`, `BSPHOTO_ALLOW_GIT_UPDATE`, `BSPHOTO_ALLOW_ZIP_UPDATE` – `app/Services/Update/WebUpdatePolicy.php` Zeilen 19–27).
- **Zielbild:** Einheitliche, korrekte Hinweiszeile in Fehlermeldungen.
- **Risiko:** low  
- **Impact:** low (UX/Dokumentation im Fehlerfall)

#### 1.3 500-Fehler: Mindest-Logging in `handleException`
- **Beschreibung:** In `Application::handleException()` Exception in PHP `error_log` oder dateibasiertes Log unter `storage/logs/` schreiben (ohne Passwörter); optional `APP_DEBUG` aus `.env` für detaillierte Ausgabe **nur** in Entwicklung.
- **Betroffene Dateien:** `app/Core/Application.php`, `public/index.php` (nur falls Konfig durchgereicht), ggf. neue winzige `Logger`-Hilfsklasse.
- **Risiko:** low  
- **Impact:** medium

#### 1.4 Security Header ergänzen (optional CSP Report-Only)
- **Beschreibung:** `SecurityHeaders::sendForApp()` (`app/Core/SecurityHeaders.php`) um sinnvolle Defaults erweitern, z. B. `Content-Security-Policy: report-only` für Admin/Public getrennt evaluieren.
- **Risiko:** low bis medium (CSP kann Inline-Skripte brechen – deshalb phased/report-only).
- **Impact:** medium

---

### Phase 2 – Strukturverbesserungen

#### 2.1 Einfachen DI-Container neben bestehender `Application` einführen (Wrapper)
- **Beschreibung:** Registrierung von Factories für `Database`, Repositories, Services; `Application` delegiert Getter an Container oder wird schrittweise zum dünnen Fassade.
- **Betroffene Dateien:** Neu z. B. `app/Core/Container.php` (oder `Infrastructure`), Refactor in `Application.php` in kleinen Schritten.
- **Zielbild:** Eine Stelle für Lebenszyklus und spätere Mockbarkeit ohne alle Aufrufer zu ändern.
- **Risiko:** medium  
- **Impact:** high

#### 2.2 Route-Metadaten für Auth (und optional CSRF)
- **Beschreibung:** Handlers als `['handler' => [Class::class, 'method'], 'auth' => true]` oder globale Defaults + Ausnahmen statt `str_starts_with` auf Namespace.
- **Betroffene Dateien:** `app/Core/Application.php` (`router()`, `applyAuthPolicy()`).
- **Risiko:** medium  
- **Impact:** medium

#### 2.3 Service-Layer pro Domäne formalisieren
- **Beschreibung:** Z. B. `CategoryService`, `MediaAdminService`, die Controller-Validierung und Orchestrierung bündeln; Repositories bleiben dünn.
- **Betroffene Dateien:** `app/Controllers/Admin/*.php`, neu unter `app/Services/Domain/` oder bestehende `Services/` erweitern.
- **Risiko:** medium  
- **Impact:** medium

#### 2.4 `Database::transaction()` für mehrstufige Schreiboperationen nutzen
- **Beschreibung:** Kandidaten identifizieren (Bulk-Operationen in `MediaController`, Import-Services), kritische Abschnitte in `$db->transaction(...)` wrappen.
- **Risiko:** medium (Deadlocks, Länge der Transaktion beachten)  
- **Impact:** medium

#### 2.5 Flash zu Queue oder strukturierten Messages erweitern
- **Beschreibung:** Mehrere Flash-Einträge oder Typ+Liste für Partial Success (vgl. Upload-Feedback in `MediaController::upload()` Zeilen 76–87 – dort wird bereits in einer Message konsolidiert).
- **Risiko:** low  
- **Impact:** low–medium

---

### Phase 3 – Tiefgreifende Refactorings

#### 3.1 Controller mit echter Constructor-Injection
- **Beschreibung:** Router/Dispatcher übergibt nicht `Application`, sondern von Container aufgelöste Abhängigkeiten (oder ein schmales `RequestContext`-Objekt).
- **Betroffene Dateien:** Alle `app/Controllers/**/*.php`, `Application::invoke()`.
- **Risiko:** high  
- **Impact:** high (Testbarkeit, klare Grenzen)

#### 3.2 Domain-Logik und „Use Cases“
- **Beschreibung:** Explizite Application Services pro Anwendungsfall (Update anwenden, Medium veröffentlichen, Import starten), Repositories nur Persistenz.
- **Risiko:** high  
- **Impact:** high bei langfristiger Evolution

#### 3.3 Event-System (optional)
- **Beschreibung:** Nachrichten wie `MediaUploaded`, `AfterZipUpdate` für Erweiterungen (z. B. externe Suchindexe).
- **Ist-Zustand:** Kein Event-Bus im Code sichtbar – optionaler Baustein.
- **Risiko:** medium–high  
- **Impact:** low bis medium (abhängig von Produkt roadmap)

---

## 4. Konkrete technische Zielbilder

### 4.1 Dependency Injection (schlichter Container)

**Ziel-Klassenstruktur (Skizze):**

```text
app/Core/Container.php          # register(string $id, callable $factory): void
                                # get(string $id): object
app/Core/Application.php        # nur noch bootstrap, get(Container), run()
app/Controllers/Admin/MediaController.php
    __construct(MediaUploadService $uploads, MediaRepository $media, ...)
```

**Beispiel-Container (illustrativ):**

```php
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, callable(self): object> */
    private array $factories = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (! isset($this->instances[$id])) {
            $this->instances[$id] = ($this->factories[$id])($this);
        }
        return $this->instances[$id];
    }
}
```

**Registrierung (illustrativ):** `Database` aus `config`, Repositories mit `Database`, `MediaUploadService` wie heute zusammensetzen (`Application.php` Zeilen 152–161).

---

### 4.2 Service Layer

**Zielbild:**

```text
app/Services/Media/MediaAdminService.php    # uploadFromRequest(), reorder(), bulkAssignCategory()
app/Services/Category/CategoryService.php    # createFromPost(), updateFromPost()
app/Controllers/Admin/MediaController.php  # dünn: parse Request, Aufruf Service, Redirect/View
```

Controller bleiben für HTTP zuständig; Entscheidungen („was ist ein gültiger Slug“, „einheitliche Fehlermeldung“) im Service.

---

### 4.3 Error Handling / Logging

**Zielbild:**

- Globaler Handler (`public/index.php`) loggt: Zeitstempel, Request-Methode, Pfad, Exception-Klasse, Message, Stack (nur wenn debug).
- Nutzer sieht weiter generische Meldung in Production; Admin ggf. Korrelations-ID im 500-Template.

**Beispiel `handleException` (illustrativ):**

```php
public static function handleException(\Throwable $e): void
{
    error_log('[BSPHOTO] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Interner Fehler.</p>';
}
```

Erweiterbar um Monolog o. Ä., ohne Framework-Zwang.

---

### 4.4 Upload-Sicherheit

**Ist (bereits stark):** `MediaUploadService::processOne()` – `is_uploaded_file`, Größe, `finfo` MIME, Whitelist, Re-Check nach Speichern (`assertMimeOnDiskMatches`), Intervention Image öffnet Datei, Thumbnail-Fehler rollt DB+Datei zurück (`persistNewMediaAtPath`, Zeilen 262–270).

**Zielbild (Feinschliff):**

- Zentrale Konstanten/Policy-Klasse für Limits (streuend aus `MediaSettings` ist bereits da – `MediaSettings::fromAppConfig`).
- Optionale Virusscan-Hooks nur über klare Schnittstelle (Service), nicht im Controller.

---

## 5. Refactoring-Strategie

### Ohne Breaking Changes migrieren (empfohlen)

1. **Zuerst** verhaltenssichernde Fixes (Phase 1.1 Git+Composer, Logging) – **keine** Signaturänderungen öffentlicher Routen.
2. **Container parallel:** `Application`-Getter intern auf `Container->get()` umbiegen; Außen API der Getter kurz beibehalten, damit Controller unverändert bleiben.
3. **Ein Controller pro Release** auf Constructor-Injection umstellen (z. B. zuerst `MediaController`), sobald Dispatcher Factories kennt.
4. **Nicht zuerst** am Gesamt-Routing und gleichzeitig an allen Controllern drehen – das erhöht Merge- und Testrisiko.

### Reihenfolge, die zwingend ist

- Zuerst **Klarheit bei Update-Ergebnissen** (Phase 1.1), damit Betrieb und Support nicht gegen „falsche“ Fehlermeldungen arbeiten.
- Danach **Logging**, damit Folge-Refactors beobachtbar sind.
- **Domain-Services** vor großem DI-Rewrite, damit weniger Logik in Controllern wandert, die noch `Application` nutzen.

### Was nicht zuerst refactored werden sollte

- Komplettes Entfernen der `Application` in einem Big Bang.
- Gleichzeitige Einführung von CSP im Report-Only-Modus **und** großem JS-Refactor ohne Test.

---

## 6. Sicherheitsmaßnahmen (konkret)

### Konkrete Risiken im IST-Code

| Risiko | Evidence | Fix-Strategie |
|--------|----------|----------------|
| Misleading „Update fehlgeschlagen“ nach Git | `GitApplicationUpdater.php` 128–132 | Composer optional/warnend wie ZIP-Pfad (Phase 1.1) |
| Keine Audit-Logs bei Authentifizierung / Update | `AuthService`, `UpdateController` – kein zentrales Log | strukturiertes Log für Login-Versuche, Update-Start/-Ende |
| 500 ohne Nachvollziehbarkeit | `Application::handleException` Zeilen 398–403 | Logging + Korrelation |
| Kein CSP | `SecurityHeaders.php` nur nosniff/frameoptions | CSP report-only evaluieren |
| Session Fixation | bereits `session_regenerate_id(true)` bei Login (`AuthService.php` 81) | beibehalten; Timeout/Idle policy prüfen (Konfiguration) |
| Bulk/Media | `MediaController` hat `reorder`, `bulk-category` (Routen in `Application.php` 376–377) | CSRF geschützt (POST global); Rechte nur „eingeloggter Admin“ – bei Mehrbenutzer später feingranular |

### Konkrete ToDos

1. **Git-Composer-Entkopplung** implementieren und mit Server ohne `composer` testen.  
2. **Fehlermeldung** in `GitApplicationUpdater` Zeile 54 an `WebUpdatePolicy`-Variablen angleichen.  
3. **`error_log` oder Datei-Logger** in `handleException` + Rotation (z. B. max. Größe) unter `storage/logs/`.  
4. **Thumb-Zugriff:** `ThumbController` prüft `isPublicGuestAccessible` (Zeilen 27–31) – bei neuen Medientypen Regeln mitziehen.  
5. **Upload:** weiterhin kein Vertrauen in Client-`type` aus `$_FILES` allein – IST nutzt `finfo` (gut); bei neuen Formaten nur über `MediaSettings` whitelisten.  
6. **GitHub-Token:** `.env` `GITHUB_API_TOKEN` – sicherstellen, dass `config/.env` nicht im Web-Root auslieferbar ist (Deployment-Check, nicht im Repo committen).

---

## Anhang: Relevante IST-Dateien (Karte)

| Bereich | Pfad |
|---------|------|
| Front Controller | `public/index.php`, `index.php` |
| App-Kern | `app/Core/Application.php`, `Request.php`, `SecurityHeaders.php`, `CsrfToken.php`, `Flash.php` |
| Datenbank | `app/Services/Database.php` |
| Auth / CSRF Middleware | `app/Middleware/AuthMiddleware.php`, `CsrfMiddleware.php`, `app/Services/AuthService.php` |
| Upload | `app/Services/Media/MediaUploadService.php`, `UploadedFiles.php` |
| Update | `app/Controllers/Admin/UpdateController.php`, `app/Services/Update/*` |
| Repositories | `app/Models/*Repository.php` |

---

## Optionale Änderungen, Überprüfungen, Verbesserungen
- werden die Metadaten der Bilder ausgelesen und irgendwo gespeichert?
- kann man die Metadaten neu einlesen lassen?
- können Metadaten für Darstellung genutztz werden (Filter, Datum wann ein Foto erstellt wurde, Reihenfolge nach Datum)?

- Wie sieht es mit den Einstellungen aus? Erkläre genauer, wofür die Basis-Url gut ist.
- Kann man Links für Externe einzelner Kategorien verschicken, sind diese im backend aufrugbar und kopierbar?

*END*
