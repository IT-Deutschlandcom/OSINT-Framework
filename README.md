# OSINT Framework – PHP/IIS Dashboard

Frameworkfreies, deutschsprachiges Dark-Dashboard mit Registrierung, manueller
Benutzerfreischaltung, sechs öffentlichen API-Adaptern und benutzergetrennter
SQL-Abfragehistorie. HTML, PHP, JavaScript und CSS; kein Composer, Node.js,
Bundler oder Build für den Betrieb nötig.

## Installation auf IIS

Voraussetzungen: IIS mit **CGI/FastCGI und PHP 8.2+**, PHP-Erweiterungen
`pdo_mysql`, `curl`, `openssl` und eine MySQL-8-/MariaDB-Datenbank (utf8mb4).
Die Anwendung verwendet **nicht Microsoft SQL Server**; eine `.sql`-Datei ist
ein Importskript, keine dateibasierte Datenbank. IIS/PHP und der Datenbankserver
müssen bereits eingerichtet sein. Für PHP-cURL muss ein gültiger CA-Truststore
vorhanden sein (`curl.cainfo` in `php.ini`); TLS-Prüfung nicht abschalten.

1. Eine leere Datenbank `osint_framework` mit Zeichensatz `utf8mb4` erstellen
   und `database.sql` in diese Datenbank importieren, beispielsweise über
   phpMyAdmin oder `mysql -u <importbenutzer> -p osint_framework < database.sql`.
2. Einen eigenen Datenbankbenutzer für diese Anwendung anlegen. Er benötigt
   `SELECT`, `INSERT`, `UPDATE` und `DELETE` auf dieser Datenbank, keine
   administrativen Rechte. Importbenutzer und Laufzeitbenutzer trennen.
3. **Den Inhalt von `public/`**, nicht das gesamte Repository, in das
   IIS-Webverzeichnis kopieren, beispielsweise `C:\inetpub\wwwroot\osint`.
   `database.sql`, Entwicklerwerkzeuge und Git-Dateien gehören nicht ins Webroot.
4. Nur die kopierte `config.php` bearbeiten: Datenbankhost, Port, Name,
   Benutzer und Passwort eintragen. `secure_cookie` für produktiven Betrieb
   auf `true` lassen. Echte Zugangsdaten niemals committen.
5. HTTPS-Binding mit gültigem Zertifikat in IIS einrichten und HTTP-Zugriffe
   am Server auf HTTPS umleiten oder sperren. PHP über FastCGI als Handler für
   `*.php` registrieren. Das geschieht bewusst nicht automatisch in `web.config`,
   weil der lokale PHP-Installationspfad je Server anders ist.
6. Die mitgelieferte `web.config` beibehalten. Sie setzt `index.php` als
   Startdokument und schützt interne Dateien. Falls IIS `500.19` meldet,
   müssen die betreffenden IIS-Konfigurationssektionen vom Administrator für
   die Site freigegeben werden. Kein URL-Rewrite-Modul erforderlich.
7. Im Browser die HTTPS-Adresse öffnen, registrieren und den Benutzer
   anschließend direkt in der Datenbank freischalten:

   ```sql
   UPDATE users SET active = 1 WHERE email = 'benutzer@example.org';
   ```

   Erst danach ist der Login möglich. Neue Konten haben immer `active = 0`.
   Es gibt weder ein Standardpasswort noch einen automatisch aktiven ersten
   Benutzer. Zum Sperren `active = 0` setzen; dies gilt auch für bestehende
   Sitzungen bei ihrer nächsten geschützten Anfrage.

Das IIS-Anwendungspoolkonto benötigt **nur Leserechte** am Webverzeichnis,
keine Schreibrechte für Anwendungscode. PHP braucht separat ein beschreibbares
Sessionverzeichnis außerhalb des Webroots. PHP-Fehler gehören in ein geschütztes
Serverlog, nicht in die Browserausgabe (`display_errors=Off` in `php.ini`).
Vor Freigabe prüfen, dass `/config.php`, `/app/bootstrap.php` und
`/app/providers.php` nicht heruntergeladen werden können. Die alte statische
Cloudflare-/D3-Auslieferung wurde entfernt: PHP-Dateien dürfen niemals mit einem
rein statischen Webserver ausgeliefert werden.

## Kostenlose Angebote und Prüfgrenzen

Der frühere Katalog enthielt 1.165 Einträge, darunter Freemium-, Bezahlangebote,
unbekannte Preise und teilweise zu großzügige `free`-Kennzeichnungen.
Er wurde deshalb **nicht blind nach Metadaten gefiltert**, sondern auf einen
konservativen Katalog mit **12 Einträgen** reduziert: sechs öffentliche
Abfragedienste sowie sechs freie lokale/browserbasierte Werkzeuge. Das ist
keine vollständige Prüfung aller früheren Angebote. Auch ungeprüfte Einträge
wurden entfernt; nicht jedes entfernte Werkzeug ist kostenpflichtig.

„Kostenlos“ bezieht sich auf das konkret eingebundene öffentliche Angebot,
nicht auf alle Produkte eines Betreibers. Hosting, Datenbank, Internetzugang
und gegebenenfalls Windows-Lizenzen sind davon unabhängig. Nutzungsgrenzen,
Datenlizenzen, faire Nutzung und Datenschutz gelten weiterhin.

**Wichtige Einschränkung:** Die offiziellen Dokumentationsseiten konnten in
der Entwicklungsumgebung wegen gesperrter DNS-/Netzwerkzugriffe nicht live
abgerufen werden. Die Adapter beruhen auf den unten referenzierten öffentlichen
Schnittstellen; aktuelle Erreichbarkeit, Bedingungen und echte externe
Antworten sind **nicht live bestätigt**. Vor Produktivbetrieb anhand dieser
Quellen und je einer autorisierten Probeabfrage überprüfen. Es gibt keine
Zusicherung unbegrenzter Nutzung oder dauerhafter Kostenfreiheit.

| Hauptkategorie / Unterkategorie | API / offizielle Referenz | Eingabe | Begrenzung |
|---|---|---|---|
| Domains & Infrastruktur / Zertifikate | [crt.sh JSON](https://crt.sh/?q=example.org&output=json) | Domain | Öffentliche JSON-Ausgabe ohne SLA; große Antworten können das lokale Antwortlimit überschreiten |
| Domains & Infrastruktur / Passive DNS | [Mnemonic](https://docs.mnemonic.no/pdns/) | Domain | Öffentlicher passiver DNS-Datensatz, kein kommerzieller Vollzugriff |
| Domains & Infrastruktur / Webarchive | [Wayback Availability](https://archive.org/help/wayback_api.php) | HTTP(S)-URL | Liefert einen verfügbaren Snapshot, keine vollständige Archivhistorie |
| Öffentliche Daten / Fahrzeuge | [NHTSA vPIC](https://vpic.nhtsa.dot.gov/api/) | VIN, optional Modelljahr | Technische US-Fahrzeugdaten; keine Halterauskunft |
| Öffentliche Daten / Wirtschaft & Länder | [World Bank v2](https://datahelpdesk.worldbank.org/knowledgebase/articles/889392-about-the-indicators-api-documentation) | Ländercode und Indikator | Begrenzte erste Ergebnisseite; keine vollständigen Massendaten |
| Öffentliche Daten / Publikationen | [Open Library Search](https://openlibrary.org/dev/docs/api/search) / [Nutzungsregeln](https://openlibrary.org/developers/api) | Suchtext | Begrenzte Suchergebnisse; für gelegentliche Abfragen, nicht zum Scraping |

Keiner dieser Adapter benötigt einen API-Schlüssel oder ein kostenpflichtiges
Konto. Die lokale Software Sherlock, WhatsMyName, Exiv2, Ghidra und Frida wird
nur verlinkt, **nicht auf dem IIS-Server installiert oder ausgeführt**.
CyberChef ist als separates freies Browserwerkzeug verlinkt. Es werden keine
kostenpflichtigen Erweiterungen oder externen Premium-APIs dieser Werkzeuge
eingebunden.

## Nutzung und Historie

- Nach dem Login Haupt- und Unterkategorie wählen oder den Katalog durchsuchen.
- Bei integrierten APIs erscheint ein zur Schnittstelle passendes Formular.
  Anbieterhinweise und Nutzungsgrenzen vor dem Absenden lesen.
- Das umrandete Ergebnisfenster liegt **oberhalb des Formulars**.
  Externe Inhalte werden als Text/JSON angezeigt, nicht als HTML ausgeführt.
- Jede ausgeführte Anbieterabfrage speichert normalisierte Eingaben, Antwort
  beziehungsweise Fehler, HTTP-Status, Anbieter, Kategorien und UTC-Zeitpunkt
  in `query_history`. Ungültige Eingaben und vorab geblockte Anfragen sind keine
  ausgeführten Anbieterabfragen und erzeugen keinen Ergebnisdatensatz.
- Die Historie zeigt nur eigene Datensätze, nach Haupt-/Unterkategorie
  gruppiert und mit Seitennavigation. „Wiedergeben“ lädt den gespeicherten
  Datensatz und befüllt das Formular, **ohne erneut den Anbieter abzufragen**.
  Erneutes Absenden legt einen neuen Datensatz an.
- Der Server verwendet feste HTTPS-Zieladressen, keine frei wählbaren
  Proxy-Ziele, keine Redirects und beschränkt Laufzeit und Antwortgröße.
  Abfragen werden lokal begrenzt; Anbieter können zusätzliche Limits setzen.
- Änderungen von Preisen oder APIs erfordern erneute Prüfung. Neue Integrationen
  brauchen einen geprüften Adapter in `public/app/providers.php` und einen
  passenden `integration`-Eintrag in `public/arf.json`; ein `api: true` allein
  erzeugt keine ausführbare Integration.

## Datenschutz und Betrieb

Abfragen werden vom Server an den ausgewählten externen Anbieter übermittelt;
dieser sieht die Server-IP und die eingereichten Suchdaten. Keine vertraulichen
Daten ohne Rechtsgrundlage/Einwilligung eingeben. Nur rechtmäßige Recherche
und ausdrücklich autorisierte Untersuchungen durchführen.

SQL-Historien enthalten möglicherweise personenbezogene oder sensible
Rechercheergebnisse. Datenbank, Backups und Sessionverzeichnis entsprechend
schützen, Zugriff begrenzen und eine passende Löschfrist festlegen.
Es gibt **keine automatische Löschung**. Ein Administrator kann beispielsweise
per geplantem Datenbankjob löschen:

```sql
DELETE FROM query_history
WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY;
```

Die Anmeldung verwendet PHP-Passworthashes, vorbereitete SQL-Anweisungen,
CSRF-Token und Sessionerneuerung. Cookies sind standardmäßig nur über HTTPS
zulässig. Registrierung und Anmeldung werden begrenzt; vorgelagerte
IIS-Limits bleiben für öffentlich erreichbare Installationen empfehlenswert.
Es gibt absichtlich weder Passwort-Reset per E-Mail noch ein webbasiertes
Administrationspanel; Freischaltung erfolgt direkt in SQL.

## Entwicklung / Prüfungen

Kein Build erforderlich. Für einen rein lokalen Test PHP mit
`php -S 127.0.0.1:8000 -t public` starten und **nur lokal** `secure_cookie`
auf `false` setzen. Der PHP-Entwicklungsserver ersetzt die IIS-Zugriffsregeln
nicht und darf nicht öffentlich erreichbar sein.

Der vorhandene CI-Workflow prüft JSON und vorhandene Auslieferungsdateien;
die geplante Linkprüfung bleibt erhalten. Manuelle Syntaxprüfungen:

```sh
python3 -m json.tool public/arf.json > /dev/null
find public -name '*.php' -exec php -l {} \;
node --check public/js/dashboard.js
```

Vor Veröffentlichung mindestens Registrierung (inaktiv), SQL-Freischaltung,
Login, erneute Sperrung, CSRF-Abweisung, benutzergetrennte Historie,
Wiedergabe, Anbieterfehler und IIS-Dateischutz prüfen.