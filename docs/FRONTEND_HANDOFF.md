# Frontend handoff – contratto API reale

Documento di integrazione tra la PWA clienti (`anticoburger-fe`, Next.js) e il backend Laravel
(`anticoburger-be`). Tutto ciò che è descritto qui è **implementato e coperto da test** (133 test
backend superati, più una verifica HTTP manuale). Ciò che non è descritto qui non esiste.

---

## 1. Ambienti

| | Frontend | Backend (API) |
|---|---|---|
| Sviluppo | `http://localhost:3000` | `http://127.0.0.1:8000/api/v1` |
| Produzione | `https://app.anticoburger.it` | `https://admin.anticoburger.it/api/v1` |

Variabile frontend suggerita: `NEXT_PUBLIC_API_BASE_URL` (senza slash finale), per esempio
`http://127.0.0.1:8000/api/v1`.

Per avviare il backend in locale: `php artisan serve` nella cartella `anticoburger-be`.
Per lavorare senza toccare i dati reali, si può avviarlo sul database di test:
`$env:DB_DATABASE='anticoburger_test'; php artisan serve --port=8010`
e usare `NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8010/api/v1`.

## 2. Regole generali

- API REST stateless, **nessuna autenticazione**, nessun cookie, nessuna sessione.
  Usare `fetch` con `credentials: "omit"`.
- Header da inviare sempre: `Accept: application/json`. Per le POST con corpo anche
  `Content-Type: application/json`.
- Successo: `{ "data": … }`. Errore: `{ "error": { "code": string, "message": string, "fields"?: … } }`.
- I messaggi in `error.message` sono già in italiano, ma il frontend deve basarsi su `error.code`.
- Date `YYYY-MM-DD` e ore `HH:mm` sono **ore locali di Europe/Rome**.
- Gli istanti (`starts_at`, `ends_at`, `cancelled_at`) sono stringhe ISO 8601 con offset
  (`2026-10-20T20:30:00+02:00`; in inverno `+01:00`).
- Il frontend non riceve né invia mai id di tavoli o altri id interni. Il tavolo viene assegnato
  dal backend.

## 3. CORS

Configurato in `config/cors.php`:

| Voce | Valore |
|---|---|
| Path | `api/*` |
| Origin consentiti | variabile `CORS_ALLOWED_ORIGINS`. Default: `http://localhost:3000,http://127.0.0.1:3000`. Produzione: `https://app.anticoburger.it` |
| Metodi | `GET`, `POST`, `OPTIONS` |
| Header consentiti | `Accept`, `Content-Type`, `X-Requested-With`, `X-Reservation-Token` |
| Header esposti | `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining` |
| Credenziali | no (`supports_credentials = false`) |
| Cache preflight | `max_age = 3600` |

- Le POST JSON e le richieste con `X-Reservation-Token` generano una richiesta di preflight `OPTIONS`.
- Da un origin non autorizzato il browser blocca la risposta (in `fetch` appare come errore di
  rete o `TypeError`).
- Per provare da smartphone in LAN (es. `http://192.168.1.50:3000`) quell'origin va aggiunto a
  `CORS_ALLOWED_ORIGINS` nel `.env` del backend, e il backend va avviato con
  `php artisan serve --host=0.0.0.0`.

## 4. Rate limiting (default, al minuto)

| Gruppo | Endpoint | Limite |
|---|---|---|
| Letture | `GET /booking-settings`, `GET /availability`, `GET /calendar` | 60 per IP, **condiviso** tra i tre endpoint |
| Creazione | `POST /reservations` | 5 per numero di telefono normalizzato + 20 per IP |
| Accesso | `GET /reservations/{reference}`, `POST /reservations/{reference}/cancel` | 30 per IP |

Oltre il limite il backend risponde `429` con `error.code = "TOO_MANY_REQUESTS"` e l'header
`Retry-After` (in secondi). Le risposte riuscite includono `X-RateLimit-Limit` e
`X-RateLimit-Remaining`.

Conseguenza pratica: non scaricare in anticipo molti mesi o molte date. Il calendario va chiesto
un mese alla volta, con cache breve.

## 5. Tipi TypeScript (corrispondono 1:1 alle API Resources Laravel)

```ts
export type ApiSuccess<T> = { data: T };

export type ApiErrorCode =
  | "VALIDATION_FAILED"          // 422, con fields
  | "DATE_OUT_OF_RANGE"          // 422
  | "MONTH_OUT_OF_RANGE"         // 422
  | "BOOKING_TOO_SOON"           // 422
  | "PARTY_SIZE_NOT_SUPPORTED"   // 422
  | "SLOT_NOT_AVAILABLE"         // 409
  | "DUPLICATE_RESERVATION"      // 409
  | "PHONE_DAILY_LIMIT_REACHED"  // 409
  | "RESERVATION_TOKEN_MISSING"  // 401
  | "RESERVATION_NOT_FOUND"      // 404
  | "NOT_FOUND"                  // 404 endpoint inesistente
  | "METHOD_NOT_ALLOWED"         // 405
  | "TOO_MANY_REQUESTS"          // 429
  | "HTTP_ERROR"                 // altri errori HTTP generici
  | "SERVER_ERROR";              // 500 (solo con APP_DEBUG=false)

export type ApiErrorBody = {
  error: {
    code: ApiErrorCode;
    message: string;
    fields?: Record<string, string[]>; // solo con VALIDATION_FAILED
  };
};

export type BookingSettings = {
  timezone: string;              // "Europe/Rome"
  min_lead_minutes: number;      // 0 = nessun anticipo minimo
  max_advance_days: number;      // 0 = nessun limite
  min_party_size: number | null; // null = nessun tavolo prenotabile
  max_party_size: number | null;
  party_sizes: number[];         // insieme ESATTO dei numeri di persone accettati, crescente
};

export type CalendarDayStatus = "available" | "fully_booked" | "closed" | "not_bookable";

export type Calendar = {
  month: string;      // "YYYY-MM"
  party_size: number;
  days: { date: string; status: CalendarDayStatus }[]; // tutti i giorni del mese, in ordine
};

export type Availability = {
  date: string;       // "YYYY-MM-DD"
  party_size: number;
  slots: string[];    // "HH:mm", ordinati, senza duplicati
};

export type CreateReservationInput = {
  date: string;        // "YYYY-MM-DD"
  time: string;        // "HH:mm" – deve essere uno degli slot restituiti
  party_size: number;
  first_name: string;
  last_name: string;
  phone: string;
  email?: string | null;
  notes?: string | null;
};

export type ReservationStatus = "confirmed" | "cancelled";

export type Reservation = {
  reference: string;          // UUID v4 pubblico
  status: ReservationStatus;
  date: string;               // "YYYY-MM-DD" locale
  time: string;               // "HH:mm" locale
  party_size: number;
  starts_at: string;          // ISO 8601 con offset
  ends_at: string;            // ISO 8601 con offset (fine prevista, calcolata dal tavolo assegnato)
  first_name: string;
  last_name: string;
  phone: string;              // come digitato dal cliente
  email: string | null;
  notes: string | null;
  cancelled_at: string | null; // ISO 8601 con offset
};

export type CreatedReservation = Reservation & {
  cancellation_token: string; // 48 caratteri [A-Za-z0-9], presente SOLO nella risposta di creazione
};
```

## 6. Endpoint

### 6.1 `GET /booking-settings`

Nessun parametro. Risposta `200`, esempio reale:

```json
{"data":{"timezone":"Europe/Rome","min_lead_minutes":30,"max_advance_days":30,"min_party_size":2,"max_party_size":4,"party_sizes":[2,3,4]}}
```

Senza tavoli attivi:

```json
{"data":{"timezone":"Europe/Rome","min_lead_minutes":30,"max_advance_days":30,"min_party_size":null,"max_party_size":null,"party_sizes":[]}}
```

Comportamento frontend:
- Il selettore del numero di persone propone **solo** i valori di `party_sizes`: possono non essere
  consecutivi (es. `[2, 4, 6]`).
- Se `party_sizes` è vuoto, mostrare lo stato "prenotazioni online non disponibili" e non avviare
  il flusso.
- I valori sono modificabili in qualsiasi momento dall'amministratore: non vanno cachati a lungo
  (staleTime di qualche minuto).

### 6.2 `GET /calendar?month=YYYY-MM&party_size=N`

| Parametro | Regole |
|---|---|
| `month` | obbligatorio, formato `Y-m` (es. `2026-10`) |
| `party_size` | obbligatorio, intero 1–20 (`BOOKING_MAX_PARTY_SIZE`) |

Risposta `200`, sempre con **tutti** i giorni del mese in ordine. Estratto reale (mese 2026-10, con
oggi 16/10, mercoledì senza fasce, 23/10 pieno, ferie il 26–27/10):

```json
{"data":{"month":"2026-10","party_size":2,"days":[
 {"date":"2026-10-15","status":"not_bookable"},
 {"date":"2026-10-16","status":"available"},
 {"date":"2026-10-21","status":"closed"},
 {"date":"2026-10-23","status":"fully_booked"},
 {"date":"2026-10-26","status":"closed"},
 {"date":"2026-10-29","status":"available"}]}}
```

| `status` | Significato | UI suggerita |
|---|---|---|
| `available` | almeno un orario libero. Equivale esattamente a "`/availability` restituisce almeno uno slot" | selezionabile |
| `fully_booked` | aperto ma tutto occupato per quel numero di persone | non selezionabile, etichetta "Completo" |
| `closed` | giorno di chiusura, blocco/ferie, oppure nessun tavolo attivo per quel numero di persone | non selezionabile, etichetta "Chiuso" |
| `not_bookable` | passato, oltre l'anticipo massimo, oppure oggi senza più orari utili | non selezionabile, attenuato |

- Mesi consultabili: dal mese corrente (in Europe/Rome) fino a +12 mesi
  (`BOOKING_CALENDAR_MAX_MONTHS_AHEAD`). Fuori intervallo: `422 MONTH_OUT_OF_RANGE`.
- Con `max_advance_days = 30` i mesi successivi sono comunque consultabili, ma i loro giorni
  risultano `not_bookable`. Il frontend può limitare la navigazione al mese che contiene
  `oggi + max_advance_days` (se `max_advance_days > 0`).
- Il dato è **indicativo** e può cambiare in qualsiasi momento.

Errori: `422 VALIDATION_FAILED` (`fields.month` / `fields.party_size`), `422 MONTH_OUT_OF_RANGE`, `429`.

### 6.3 `GET /availability?date=YYYY-MM-DD&party_size=N`

| Parametro | Regole |
|---|---|
| `date` | obbligatorio, `Y-m-d` |
| `party_size` | obbligatorio, intero 1–20 |

Risposta `200`, esempio reale:

```json
{"data":{"date":"2026-10-20","party_size":2,"slots":["19:00","19:30","20:00","20:30","21:00","21:30","22:00","22:30","23:00"]}}
```

- `slots` sono **orari di inizio**. L'ultimo orario della fascia è incluso (23:00 significa iniziare
  alle 23:00).
- La durata dipende dal tavolo che verrà assegnato, quindi **l'orario di fine non è noto prima
  della conferma**: non mostrarlo prima di avere `ends_at` dalla risposta di creazione.
- `slots: []` significa nessun orario libero (chiuso, completo, oppure numero di persone non
  supportato).
- Gli orari già passati o entro `min_lead_minutes` da adesso sono esclusi.
- Data passata o oltre `max_advance_days`: `422 DATE_OUT_OF_RANGE` (non `slots: []`).
- Dato **informativo**: non riserva nulla. Rileggerlo quando l'utente arriva allo step orario e dopo
  ogni `409`.

Errori: `422 VALIDATION_FAILED`, `422 DATE_OUT_OF_RANGE`, `429`.

### 6.4 `POST /reservations`

Corpo JSON:

| Campo | Obbligatorio | Regole backend |
|---|---|---|
| `date` | sì | `Y-m-d` |
| `time` | sì | `H:i` esatto (`"20:30"`; `"20:30:00"` è rifiutato). Deve essere uno slot valido, altrimenti `409 SLOT_NOT_AVAILABLE` |
| `party_size` | sì | intero 1–20 |
| `first_name` | sì | stringa ≤ 100 caratteri (qualsiasi carattere: apostrofi, accenti, trattini sono ammessi) |
| `last_name` | sì | stringa ≤ 100 caratteri |
| `phone` | sì | stringa ≤ 30 caratteri, deve essere normalizzabile (vedi sotto) |
| `email` | no | email valida ≤ 190 caratteri, oppure `null` / assente |
| `notes` | no | stringa ≤ 500 caratteri, oppure `null` / assente |

- Gli spazi iniziali e finali vengono rimossi; le stringhe vuote diventano `null`, quindi un campo
  obbligatorio vuoto produce errore.
- Campi sconosciuti (es. `restaurant_table_id`) vengono **ignorati**.

Normalizzazione del telefono (formato E.164, usata per limite giornaliero, duplicati e rate limit):
- vengono rimossi spazi, `-`, `.`, `/`, `(`, `)`; `00` iniziale diventa `+`;
- senza prefisso: numero italiano di 6–11 cifre, lo zero iniziale dei fissi è mantenuto
  (`333 123 4567` → `+393331234567`, `06 1234567` → `+39061234567`);
- con `+39` / `0039`: si applica la stessa regola italiana;
- altri prefissi `+XX`: 8–15 cifre totali (`+44 20 7946 0958` è valido);
- non valido: lettere, meno di 6 cifre, più di 11 cifre senza prefisso, `+0…`.
  Esempio: `"39 3331234567"` (12 cifre, senza `+`) viene rifiutato.

Ordine dei controlli lato server (utile per capire quale errore arriva):
1. validazione formale → `422 VALIDATION_FAILED`;
2. data fuori finestra → `422 DATE_OUT_OF_RANGE`; orario passato o entro l'anticipo minimo →
   `422 BOOKING_TOO_SOON`;
3. nessun tavolo attivo accetta `party_size` → `422 PARTY_SIZE_NOT_SUPPORTED`;
4. prenotazione confermata identica (stesso telefono normalizzato, data, ora, persone), se la
   protezione è attiva → `409 DUPLICATE_RESERVATION`;
5. raggiunto il massimo di prenotazioni confermate per quel telefono **nel giorno della
   prenotazione** (default 2) → `409 PHONE_DAILY_LIMIT_REACHED`;
6. nessun tavolo compatibile libero in quell'orario → `409 SLOT_NOT_AVAILABLE`.

I passi 2–6 avvengono dentro una transazione con lock: due clienti contemporanei sull'ultimo tavolo
producono una sola prenotazione, e l'altro riceve `409 SLOT_NOT_AVAILABLE` (verificato con processi
concorrenti reali su MySQL).

Risposta `201`, esempio reale:

```json
{"data":{"reference":"0afd8af6-ba21-49eb-b53a-f61ceb0d4105","status":"confirmed","date":"2026-10-20","time":"20:30","party_size":2,"starts_at":"2026-10-20T20:30:00+02:00","ends_at":"2026-10-20T21:00:00+02:00","first_name":"Mario","last_name":"Rossi","phone":"333 123 4567","email":"mario@example.com","notes":"Zona tranquilla","cancelled_at":null,"cancellation_token":"hS3IfT912aMyhYNryf9dAB7OvHjJikixWeBp4fQZd9H0IR4c"}}
```

Esempi reali di errore:

```json
{"error":{"code":"SLOT_NOT_AVAILABLE","message":"L'orario selezionato non è più disponibile."}}
```

```json
{"error":{"code":"VALIDATION_FAILED","message":"I dati inviati non sono validi.","fields":{"date":["Il campo data deve essere nel formato Y-m-d."],"time":["Il campo ora è obbligatorio."],"party_size":["Il campo numero di persone è obbligatorio."],"first_name":["Il campo nome è obbligatorio."],"last_name":["Il campo cognome è obbligatorio."],"phone":["Il numero di telefono non è valido."]}}}
```

**La POST non è idempotente.** Non va ripetuta automaticamente: in caso di timeout la prenotazione
potrebbe essere già stata creata (vedi §10).

### 6.5 `GET /reservations/{reference}`

Header obbligatorio: `X-Reservation-Token: <cancellation_token>`.

- `200`: un `Reservation` (stesso schema della creazione, **senza** `cancellation_token`). Funziona
  anche per le prenotazioni annullate (`status: "cancelled"`).
- `401 RESERVATION_TOKEN_MISSING`: header assente o vuoto.
- `404 RESERVATION_NOT_FOUND`: token errato, riferimento inesistente o non UUID. I casi sono
  indistinguibili per scelta di sicurezza.

### 6.6 `POST /reservations/{reference}/cancel`

Header obbligatorio: `X-Reservation-Token`. Nessun corpo.

- `200`: il `Reservation` aggiornato con `status: "cancelled"` e `cancelled_at` valorizzato.
- È idempotente: se la prenotazione è già annullata risponde di nuovo `200` con gli stessi dati.
  Il frontend riconosce il caso perché `status` era già `cancelled` prima della chiamata.
- Nessun limite temporale: si può annullare anche poco prima dell'orario.
- Errori: `401 RESERVATION_TOKEN_MISSING`, `404 RESERVATION_NOT_FOUND`, `429`.

Non esiste un endpoint per **modificare** una prenotazione: il cliente annulla e prenota di nuovo.

## 7. Tabella completa degli errori

| HTTP | `code` | Quando | Messaggio backend |
|---|---|---|---|
| 401 | `RESERVATION_TOKEN_MISSING` | dettaglio/annullamento senza header | Per accedere alla prenotazione è necessario il codice segreto (header X-Reservation-Token). |
| 404 | `RESERVATION_NOT_FOUND` | token o riferimento errati | Prenotazione non trovata. |
| 404 | `NOT_FOUND` | URL API inesistente | Risorsa non trovata. |
| 405 | `METHOD_NOT_ALLOWED` | metodo errato | Metodo HTTP non consentito. |
| 409 | `SLOT_NOT_AVAILABLE` | orario non (più) libero o non valido | L'orario selezionato non è più disponibile. |
| 409 | `DUPLICATE_RESERVATION` | prenotazione identica già presente | Esiste già una prenotazione identica per questo numero di telefono. |
| 409 | `PHONE_DAILY_LIMIT_REACHED` | troppe prenotazioni stesso telefono, stesso giorno | È stato raggiunto il numero massimo di prenotazioni per questo numero di telefono nella giornata. |
| 422 | `VALIDATION_FAILED` | dati non validi, dettagli in `fields` | I dati inviati non sono validi. |
| 422 | `PARTY_SIZE_NOT_SUPPORTED` | numero di persone non accettato da alcun tavolo attivo | Non ci sono tavoli prenotabili per il numero di persone indicato. |
| 422 | `DATE_OUT_OF_RANGE` | data passata o troppo lontana | La data selezionata non è prenotabile. |
| 422 | `MONTH_OUT_OF_RANGE` | mese del calendario fuori intervallo | Il mese selezionato non è consultabile. |
| 422 | `BOOKING_TOO_SOON` | orario passato o entro l'anticipo minimo | L'orario selezionato è troppo vicino: scegli un orario successivo. |
| 429 | `TOO_MANY_REQUESTS` | rate limit | Troppe richieste. Riprova tra poco. |
| 500 | `SERVER_ERROR` | errore imprevisto (`APP_DEBUG=false`) | Si è verificato un errore imprevisto. Riprova più tardi. |

Note:
- In **sviluppo** con `APP_DEBUG=true` un errore 500 restituisce il JSON standard di Laravel
  (`message`, `exception`, `file`, `trace`) e **non** l'envelope `error`. Il client HTTP deve
  gestire qualsiasi corpo non conforme come errore generico.
- Il backend contiene altri due codici, `TABLE_NOT_AVAILABLE` e `RESERVATION_CANCELLED`, che
  riguardano solo il pannello admin e **non arrivano mai** alle API pubbliche.
- Possibili risposte non JSON: errori di rete, CORS bloccato, proxy o CDN (502/503/504 HTML), timeout.

## 8. Fuso orario

- La data "oggi" deve essere calcolata in **Europe/Rome**, non nel fuso del dispositivo:
  `new Intl.DateTimeFormat("en-CA", { timeZone: "Europe/Rome" }).format(new Date())` restituisce `YYYY-MM-DD`.
- Non usare `new Date("2026-10-20")`: viene interpretata come UTC e può slittare di un giorno.
  Trattare le date come stringhe e formattarle con `Intl.DateTimeFormat("it-IT", { timeZone: "Europe/Rome", … })`.
- Per mostrare la prenotazione usare `date` e `time` della risposta. `starts_at`/`ends_at` servono
  per calcoli e per l'evento calendario (`.ics`).
- I cambi d'ora legale sono gestiti dal backend (es. il 25/10/2026 gli istanti passano da `+02:00`
  a `+01:00`).

## 9. Token di annullamento

- Generato solo per le prenotazioni create dalla PWA, lungo 48 caratteri alfanumerici, restituito
  **solo** nella risposta `201`. Nel database c'è solo il suo hash: se viene perso non è
  recuperabile in alcun modo via API.
- Non esiste un recupero tramite telefono o email (scelta di sicurezza). Se il cliente perde il
  token deve contattare il ristorante.
- Le prenotazioni inserite dal ristorante (pannello admin) **non** hanno token e non sono
  consultabili dalla PWA.
- Raccomandazioni:
  - salvare `reference` + `token` sul dispositivo (es. `localStorage`) per mostrare "Le mie
    prenotazioni" su quel dispositivo;
  - offrire un link personale di gestione con i dati nel **frammento** dell'URL, che non viene
    inviato ai server né finisce nei log: `https://app.anticoburger.it/prenotazione#ref=<reference>&token=<token>`.
    Il link si può copiare o condividere;
  - mostrare anche un riferimento breve leggibile (es. prime 8 cifre di `reference`) solo come
    riferimento verso il ristorante: non è sufficiente per annullare;
  - il token non deve finire in log, analytics, URL di query (`?token=`), header `Referer`
    o report di errori.

## 10. Comportamenti critici per il frontend

1. **Mai mostrare "confermata" prima del `201`.**
2. **Conflitto `409 SLOT_NOT_AVAILABLE`:** mostrare un messaggio chiaro, invalidare e ricaricare
   gli slot della data (e il calendario del mese), riportare l'utente alla scelta dell'orario
   **conservando i dati anagrafici** già inseriti.
3. **Timeout o errore di rete sulla POST:** l'esito è ignoto. Non ripetere automaticamente. Dire al
   cliente che non è possibile confermare l'esito.
   - Se il cliente riprova con gli stessi dati e riceve `DUPLICATE_RESERVATION`, la prenotazione
     precedente esiste, ma il token è andato perso: invitarlo a contattare il ristorante.
4. **Disponibilità volatile:** calendario e slot non devono essere conservati come dati
   affidabili. Usare una cache breve e rileggerli quando la pagina torna in primo piano.
   Non usarli mai offline.
5. **`BOOKING_TOO_SOON`** può comparire se il cliente resta a lungo sul form: ricaricare gli slot.
6. **`PARTY_SIZE_NOT_SUPPORTED`** può comparire se l'admin cambia le capienze durante il flusso:
   ricaricare `booking-settings` e riportare allo step persone.
7. **Offline:** nessuna prenotazione né annullamento senza connessione.

## 11. Cosa NON esiste nel backend (non implementare chiamate)

- Login, account, registrazione clienti.
- Ricerca prenotazioni per telefono o email.
- Modifica di una prenotazione da parte del cliente.
- Informazioni sul ristorante (indirizzo, orari, contatti): vanno inserite come contenuto statico
  del frontend.
- Notifiche email, SMS, WhatsApp, push.
- Chiave di idempotenza per la POST.
- Scelta del tavolo da parte del cliente.

## 12. Impostazioni modificabili dall'admin che influenzano il frontend

| Impostazione | Default | Effetto |
|---|---|---|
| `min_lead_minutes` | 30 | orari troppo vicini esclusi da slot e calendario, `BOOKING_TOO_SOON` |
| `max_advance_days` | 30 | giorni oltre il limite `not_bookable`, `DATE_OUT_OF_RANGE` |
| `max_reservations_per_phone_per_day` | 2 | `PHONE_DAILY_LIMIT_REACHED` (le annullate non contano) |
| `prevent_duplicate_bookings` | attivo | `DUPLICATE_RESERVATION` |
| Tavoli, capienze, fasce, blocchi | – | cambiano `party_sizes`, calendario e slot in tempo reale |

## 13. Verifiche backend già eseguite

- 133 test automatici superati (Unit, Feature, Filament, API, concorrenza reale su MariaDB).
- Flusso HTTP reale: disponibilità → prenotazione → slot rimosso → conflitto 409 → dettaglio con
  token → 401 senza token → 404 con token errato → annullamento → annullamento idempotente →
  slot di nuovo libero → blocco rispettato → tavolo disattivato non usato → calendario.
- Preflight CORS da `http://localhost:3000` consentito; da origin estranei nessun header
  `Access-Control-Allow-Origin`.
