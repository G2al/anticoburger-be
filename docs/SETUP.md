# Setup – Antico Burger backend

Requisiti: PHP ≥ 8.2 (pdo_mysql, intl, mbstring), Composer, MySQL/MariaDB con InnoDB.

## Avvio in locale

```powershell
composer install
copy .env.example .env      # solo per una nuova installazione: non sovrascrivere un .env esistente
php artisan key:generate    # solo per una nuova installazione
php artisan migrate         # migrations non distruttive
php artisan serve           # http://127.0.0.1:8000
```

Non usare mai `migrate:fresh` sul database reale: cancella tutti i dati.

## Database

Nel file `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anticoburger
DB_USERNAME=...
DB_PASSWORD=...
```

La migration `create_booking_settings_table` crea la riga singleton con i valori iniziali.
`BookingSettings::current()` la ricrea in modo idempotente se manca.

Dati demo (facoltativi, mai eseguiti in automatico; i tavoli con lo stesso nome non vengono toccati):

```powershell
php artisan db:seed --class=DemoSeeder
```

## Pannello Filament

URL: `http://127.0.0.1:8000/admin`. Possono accedere solo gli utenti con `users.is_admin = 1`.

Per autorizzare un utente esistente:

```powershell
php artisan app:grant-admin admin@anticoburger.it
```

Per revocare l'accesso:

```powershell
php artisan app:grant-admin email@esempio.it --revoke
```

Nota: in questo ambiente l'utente `admin@anticoburger.it` è già stato autorizzato con il comando sopra.
Per creare un nuovo utente da console: `php artisan make:filament-user`, poi `app:grant-admin`.

## Notifiche push del gestionale (Web Push + VAPID)

Gli amministratori possono ricevere una notifica su telefono o PC per ogni **nuova prenotazione
online confermata**. Le prenotazioni inserite dal pannello non generano notifiche.

1. Genera le chiavi **una sola volta**:
   ```bash
   php artisan app:vapid-keys
   ```
2. Copia le tre righe stampate nel `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`),
   poi esegui `php artisan optimize`. Non cambiare più le chiavi: chiavi nuove invalidano tutti i
   dispositivi registrati.
3. Da ogni dispositivo tocca l'icona **campanella** nell'header del pannello, accanto all'avatar.
   Campanella barrata significa disattivate; campanella piena significa attive. All'attivazione
   arriva subito una notifica di prova. Un nuovo tocco disattiva le notifiche su quel dispositivo.

Requisiti e limiti:
- HTTPS obbligatorio. Estensioni PHP `openssl`, `curl`, `mbstring` (`gmp` consigliata).
- iPhone/iPad: iOS 16.4 o successivo, **solo dall'app installata** sulla schermata Home.
- Android/desktop: Chrome, Edge e Firefox, anche senza installazione. Non in navigazione privata.
- L'invio avviene dopo la risposta al cliente. Gli errori dei servizi push vengono registrati nel
  log e non bloccano mai la prenotazione. I dispositivi disinstallati vengono rimossi in automatico.
- Su Windows/XAMPP la generazione delle chiavi e la cifratura richiedono la variabile `OPENSSL_CONF`
  (es. `C:\xampp\php\extras\ssl\openssl.cnf`) impostata **prima** di avviare PHP. Su Linux non serve.

## CORS

In `.env`, origin separati da virgola:

```
# sviluppo
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
# produzione
CORS_ALLOWED_ORIGINS=https://app.anticoburger.it
```

La configurazione vale solo per `/api/*`, con metodi GET/POST/OPTIONS e header `X-Reservation-Token`.
CORS **non** è una protezione di sicurezza: decide solo quali siti possono leggere le risposte
dal browser.

Dietro un reverse proxy o una CDN, impostare `TRUSTED_PROXIES`: altrimenti il rate limit vede l'IP
del proxy invece di quello del cliente.

## Produzione

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.anticoburger.it
CORS_ALLOWED_ORIGINS=https://app.anticoburger.it
```

Poi:

```bash
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
```

## Test

```powershell
php artisan test
```

- **Test Unit e Feature**: SQLite in memoria (vedi `phpunit.xml`). Non toccano mai il database
  di sviluppo.
- **Test di concorrenza** (`tests/Concurrency`): usano un database MySQL dedicato, di default
  `anticoburger_test`, configurabile con `DB_TEST_DATABASE`. Il nome deve finire con `_test`.
  Il test esegue `migrate:fresh` **solo** su quel database e avvia processi PHP separati.
  Se il database non è raggiungibile il test viene saltato (*skipped*).

Creazione del database di test, una tantum:

```sql
CREATE DATABASE anticoburger_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Per eseguire singole suite:

```powershell
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Concurrency
```

Stile del codice:

```powershell
vendor/bin/pint --test   # verifica
vendor/bin/pint          # correzione
```

## Test manuali con Postman

1. Crea dal pannello un tavolo con capienze e almeno una fascia nel giorno da provare.
2. Imposta l'header `Accept: application/json` (e `Content-Type: application/json` per le POST).
3. `GET {{base}}/api/v1/booking-settings`
4. `GET {{base}}/api/v1/availability?date=AAAA-MM-GG&party_size=2`
5. `POST {{base}}/api/v1/reservations` con il body JSON (vedi `docs/API.md`), poi salva
   `data.reference` e `data.cancellation_token` in variabili della collection.
6. `GET {{base}}/api/v1/reservations/{{reference}}` con l'header `X-Reservation-Token: {{token}}`.
7. `POST {{base}}/api/v1/reservations/{{reference}}/cancel` con lo stesso header.

Per provare le API senza toccare i dati reali, puoi avviare il server sul database di test:

```powershell
$env:DB_DATABASE='anticoburger_test'; php artisan serve --port=8010
```

## Variabili `.env` aggiuntive

| Variabile | Default | Uso |
|---|---|---|
| `BOOKING_TIMEZONE` | `Europe/Rome` | fuso orario del ristorante |
| `BOOKING_MAX_PARTY_SIZE` | `20` | capienza massima configurabile e richiedibile |
| `BOOKING_CALENDAR_MAX_MONTHS_AHEAD` | `12` | mesi futuri consultabili dal calendario pubblico |
| `BOOKING_RATE_LIMIT_READ` | `60` | richieste al minuto per IP su settings/availability/calendar |
| `BOOKING_RATE_LIMIT_BOOKING_PER_PHONE` | `5` | creazioni al minuto per numero di telefono |
| `BOOKING_RATE_LIMIT_BOOKING_PER_IP` | `20` | creazioni al minuto per IP |
| `BOOKING_RATE_LIMIT_ACCESS` | `30` | richieste al minuto per IP su dettaglio/annullamento |
| `TRUSTED_PROXIES` | vuoto | IP dei proxy fidati (oppure `*`) |
| `CORS_ALLOWED_ORIGINS` | localhost:3000 | origin autorizzati |
| `DB_TEST_DATABASE` | `anticoburger_test` | database MySQL per i test di concorrenza |
