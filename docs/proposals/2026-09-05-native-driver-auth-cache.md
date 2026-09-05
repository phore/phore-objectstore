# Proposal: Abhängigkeitsfreie native S3- und Google-Treiber

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-09-05 | dermatthes | §§ 1–9: Proposal angelegt |

## § 1 Kurzfassung

ObjectStore erhält eine kleine interne HTTP-, TLS- und Authentifizierungsschicht. Der S3-Treiber spricht die S3-REST-API direkt mit AWS Signature Version 4 an; der Google-Treiber nutzt die bestehende JSON-API ohne `phore/http-client` und verwendet ein gemeinsames, ablaufzeitgesteuertes Authentifizierungs-Caching. Der Standardbetrieb soll danach keine Composer-Laufzeitabhängigkeiten außerhalb dieses Pakets benötigen; SDK-basierte Adapter bleiben optional.

## § 2 Ausgangslage

`composer.json` verlangt derzeit `phore/core` und `phore/http-client`. `S3ObjectStoreDriver` verwendet `aws/aws-sdk-php`; `GoogleObjectStoreDriver` verwendet `google/cloud-storage`. Der eigene REST-basierte `PhoreGoogleObjectStoreDriver` verwendet wiederum `phore_http_request()`, lädt Schlüsselmaterial je Instanz und setzt die Token-Gültigkeit unabhängig von der OAuth-Antwort pauschal auf 300 Sekunden. Zusätzlich sind die URI-Schemata zwischen README, `ObjectStore::Connect()` und `ObjectStoreDriverFactory::Build()` nicht durchgehend gleich zugeordnet.

Die Folgen sind eine unnötig große transitive Supply-Chain-Fläche, SDK-Bootstrap-Kosten, wiederholtes Lesen und Parsen von Schlüsselmaterial sowie vermeidbare OAuth-Token-Requests bei mehreren Driver-Instanzen oder kurzlebigen PHP-Workern.

## § 3 Ziele und Nicht-Ziele

Ziele sind ein eigenständig installierbarer Standardpfad für S3 und Google Cloud Storage, weniger Authentifizierungs- und Verbindungsaufwand, sichere TLS-Vorgaben, vollständige Testbarkeit ohne echte Cloud-Zugangsdaten und weitgehende Kompatibilität der öffentlichen ObjectStore-API.

Nicht Teil dieses Vorhabens sind neue Storage-Features außerhalb der bestehenden Driver-Schnittstelle, ein eigener allgemeiner HTTP-Client als separates Paket, das Abschalten der TLS-Prüfung oder das Persistieren privater Schlüssel. Azure- und SDK-basierte Adapter werden nicht neu implementiert; sie bleiben optionale Integrationen.

## § 4 Interne Connection- und TLS-Schicht

Unter `src/Connection/` wird ein minimaler, paketinterner Transport aus Request, Response, HTTP-Client und TLS-Konfiguration eingeführt. Er deckt ausschließlich die von ObjectStore benötigten Methoden, Header, Bodies, Streams, Timeouts und Statuscodes ab. Primär wird cURL mit wiederverwendbaren Handles und Keep-Alive eingesetzt; fehlt `ext-curl`, steht ein PHP-Stream-Fallback ohne Zusatzpaket bereit.

TLS prüft standardmäßig Zertifikatskette und Hostnamen. Unterstützt werden System-CA, ein explizites `caFile` sowie optional `clientCert` und `clientKey`; unsichere globale Abschalter werden nicht angeboten. Zertifikats- und Schlüsseldateien werden anhand von kanonischem Pfad, Dateigröße und Änderungszeit invalidiert. Geöffnete OpenSSL-Schlüsselobjekte dürfen pro Prozess wiederverwendet, aber weder serialisiert noch protokolliert werden.

Der Transport begrenzt Redirects, Header- und Fehler-Body-Größen, setzt getrennte Connect-/Request-Timeouts und bildet Netzwerkfehler sowie HTTP-Statuscodes auf paketinterne Exceptions ab. Dadurch teilen S3 und Google dieselbe Connection-Schicht, ohne `phore/http-client` oder ein PSR-HTTP-Paket vorauszusetzen.

## § 5 Authentifizierungs-Caching

Eine kleine interne `AuthenticationCache`-Schnittstelle erhält einen In-Memory-Cache als Standard. Ein optionaler File-Cache kann für kurzlebige Worker explizit aktiviert werden; er verwendet ein Verzeichnis mit Modus 0700, Dateien mit Modus 0600, atomare Ersetzung und `flock()`. Cache-Schlüssel enthalten nur Hashes der Identität und des Geltungsbereichs, niemals Zugangsdaten.

Für Google werden das geparste OpenSSL-Schlüsselobjekt und OAuth-Access-Tokens geteilt. Die Ablaufzeit wird aus `expires_in` berechnet und um einen konfigurierbaren Sicherheitsvorlauf reduziert. Ein Refresh-Lock verhindert parallele Token-Erneuerungen; ein fehlgeschlagener Refresh ersetzt kein noch gültiges Token. Änderungen an der Key-Datei invalidieren Schlüssel und Token.

Für S3 wird der abgeleitete SigV4-Signing-Key nach UTC-Datum, Region, Service und gehashter Credential-ID gecacht. Explizite Credentials, Umgebungsvariablen sowie temporäre ECS-/IMDSv2-Credentials werden unterstützt; Session-Token und deren reale Ablaufzeit fließen in Signatur und Cache-Invalidierung ein. Private Schlüssel, Secret Access Keys und abgeleitete Signing-Keys werden nicht dauerhaft auf Platte geschrieben.

## § 6 Nativer S3-Treiber

`S3ObjectStoreDriver` wird vom AWS-SDK entkoppelt und verwendet die interne Connection-Schicht. Implementiert werden `HEAD`, `GET`, `PUT`, `DELETE`, serverseitiges Kopieren und `ListObjectsV2` einschließlich Pagination, Präfix und Continuation Token. `has()` verwendet `HEAD` statt eines vollständigen Downloads. Upload- und Download-Streams werden ohne vollständige zusätzliche Kopie im Speicher verarbeitet, soweit der verwendete PHP-Transport dies zulässt.

Die SigV4-Implementierung kapselt kanonische URI-/Query-Kodierung, Header-Normalisierung, Payload-Hashing und Clock-Skew-Behandlung. Ein konfigurierbarer HTTPS-Endpunkt sowie Virtual-Host- und Path-Style-Modus ermöglichen AWS S3 und kompatible Object Stores. Fehlermeldungen enthalten Request-ID und Status, aber keine Credentials oder Signaturen.

## § 7 Nativer Google-Treiber

Der bestehende REST-basierte Google-Treiber wird auf die interne Connection- und Auth-Schicht umgestellt. Upload, Download, Metadaten, Löschen, Kopieren und Listen behalten die bestehende Driver-Semantik; Objektpfade werden zentral RFC-konform kodiert. Token-Endpunkte müssen standardmäßig HTTPS verwenden. Wiederholungen gelten nur für idempotente oder sicher wiederholbare Requests und nutzen begrenztes exponentielles Backoff mit Jitter.

Der dependency-freie REST-Treiber wird der kanonische `gcs`-Pfad. SDK-basierte Treiber bleiben bei installiertem Google-SDK nutzbar. Die bisherigen Aliase `gcsnd` und `gcs+phore` sowie `s3nd` werden zunächst kompatibel geroutet und in der Dokumentation als Legacy-Aliase gekennzeichnet, damit bestehende Connection Strings nicht unangekündigt brechen.

## § 8 Abhängigkeiten und Kompatibilität

Die produktiven Standardpfade dürfen in `composer.json` nur PHP und tatsächlich benötigte PHP-Extensions verlangen. `phore/core`, `phore/http-client` und das AWS-SDK entfallen als verpflichtende Laufzeitabhängigkeiten. SDKs für Google und Azure bleiben unter `suggest` beziehungsweise `require-dev` und werden ausschließlich bei expliziter Wahl ihres Adapters geladen.

Verwendete Helfer für URL-Parsing, JSON, Dateien und Not-Found-Exceptions werden klein und paketintern umgesetzt. Öffentliche Konstruktoren, `ObjectStoreDriver` und Connection Strings bleiben soweit möglich kompatibel. Wo der Wechsel von `Phore\\Core\\Exception\\NotFoundException` auf eine paketinterne Exception nicht ohne externe Basisklasse möglich ist, erfolgt er in einer Hauptversion und wird mit einer Migrationsnotiz dokumentiert; bei vorhandenem `phore/core` kann zusätzlich ein Kompatibilitätsadapter angeboten werden.

## § 9 Nutzen, Prüfung und Rollout

Erwarteter Nutzen sind weniger installierte Fremdpakete und damit weniger zu beobachtende Supply-Chain-Komponenten, geringere Startzeit durch entfallende SDK-Initialisierung, weniger OAuth-Requests und OpenSSL-Key-Parses sowie weniger TCP/TLS-Handshakes durch Connection-Reuse. Diese Aussagen werden vor dem Merge durch Benchmarks für Driver-Konstruktion, ersten Request, Folge-Requests und mehrere kurzlebige Instanzen belegt; das Proposal behauptet keine pauschale prozentuale Beschleunigung ohne Messung.

Akzeptanzkriterien sind: AWS-SigV4-Golden-Tests gegen veröffentlichte AWS-Testvektoren; Unit-Tests für kanonische Kodierung, Cache-Hit, Ablauf, Sicherheitsvorlauf, Key-Datei-Änderung und parallelen Refresh; Transporttests für TLS-Prüfung, Timeouts, Streams und Fehlerabbildung; credential-freie Mock-Tests für S3 und Google; opt-in Integrationstests gegen echte Buckets; sowie ein `composer install --no-dev`, das außer dem Root-Paket nur Plattformanforderungen benötigt. Der Rollout erfolgt in getrennten, reviewbaren Implementierungs-PRs für Connection-Schicht, Google, S3 und abschließende Dependency-/Dokumentationsbereinigung.
