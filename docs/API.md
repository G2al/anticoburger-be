# API pubblica v1

Base URL: `https://admin.anticoburger.it/api/v1` (sviluppo: `http://127.0.0.1:8000/api/v1`).

- API stateless, nessun login cliente.
- Inviare sempre `Accept: application/json`; per le richieste POST anche `Content-Type: application/json`.
- Date `YYYY-MM-DD` e ore `HH:MM` sono nel fuso del ristorante (`Europe/Rome`).
  Gli istanti sono in ISO 8601 con offset.
- Le risposte di successo hanno la forma `{ "data": … }`, gli errori `{ "error": { "code", "message" } }`.

## Endpoint

| Metodo | Percorso | Rate limit (default/min) |
|---|---|---|
| GET | `/booking-settings` | 60 per IP |
| GET | `/availability` | 60 per IP |
| GET | `/calendar` | 60 per IP (limite condiviso con le altre letture) |
| POST | `/reservations` | 5 per telefono + 20 per IP |
| GET | `/reservations/{reference}` | 30 per IP |
| POST | `/reservations/{reference}/cancel` | 30 per IP |

### GET /booking-settings

Restituisce le regole utili al frontend. Non espone informazioni sui singoli tavoli.

```json
{
  "data": {
    "timezone": "Europe/Rome",
    "min_lead_minutes": 30,
    "max_advance_days": 30,
    "min_party_size": 2,
    "max_party_size": 10,
    "party_sizes": [2, 3, 4, 6, 8, 10]
  }
}
```

- `party_sizes` è l'insieme esatto delle capienze prenotabili (tavoli attivi).
  `min`/`max` **non** garantiscono che i valori intermedi siano accettati.
- Senza tavoli prenotabili: `min_party_size` e `max_party_size` valgono `null`, `party_sizes` vale `[]`.
- `max_advance_days = 0` significa nessun limite, `min_lead_minutes = 0` nessun anticipo minimo.

### GET /availability?date=YYYY-MM-DD&party_size=N

```json
{ "data": { "date": "2026-10-17", "party_size": 6, "slots": ["19:00", "19:30", "20:30", "21:00"] } }
```

Gli orari sono ordinati e senza duplicati. `slots: []` significa nessuna disponibilità.
La risposta è informativa e non riserva lo slot.

Errori: `422 VALIDATION_FAILED` (parametri non validi), `422 DATE_OUT_OF_RANGE` (data passata o
oltre `max_advance_days`).

### GET /calendar?month=YYYY-MM&party_size=N

Stato di ogni giorno del mese per il numero di persone indicato (un mese per richiesta).

```json
{
  "data": {
    "month": "2026-10",
    "party_size": 2,
    "days": [
      { "date": "2026-10-15", "status": "not_bookable" },
      { "date": "2026-10-16", "status": "available" },
      { "date": "2026-10-21", "status": "closed" },
      { "date": "2026-10-23", "status": "fully_booked" }
    ]
  }
}
```

`days` contiene sempre tutti i giorni del mese in ordine (l'estratto sopra è abbreviato).

| `status` | Significato |
|---|---|
| `available` | almeno un orario libero: `/availability` per quella data restituisce almeno uno slot |
| `fully_booked` | aperto, ma tutti gli orari sono già occupati |
| `closed` | nessuna fascia prenotabile (giorno di chiusura, nessun tavolo attivo per quel numero di persone) oppure tutti gli orari coperti da blocchi |
| `not_bookable` | data passata, oltre `max_advance_days`, oppure oggi senza più orari dopo l'anticipo minimo |

Mesi consultabili: dal mese corrente (fuso Europe/Rome) ai successivi `BOOKING_CALENDAR_MAX_MONTHS_AHEAD`
(default 12). Fuori da questo intervallo: `422 MONTH_OUT_OF_RANGE`. Parametri non validi: `422 VALIDATION_FAILED`.
Il risultato è informativo: la prenotazione ricontrolla sempre tutto.

### POST /reservations

```json
{
  "date": "2026-10-17",
  "time": "20:30",
  "party_size": 6,
  "first_name": "Mario",
  "last_name": "Rossi",
  "phone": "3331234567",
  "email": "mario@example.com",
  "notes": "Preferibilmente zona tranquilla"
}
```

| Campo | Regole |
|---|---|
| `date` | obbligatorio, `Y-m-d` |
| `time` | obbligatorio, `H:i`, deve essere uno slot generato |
| `party_size` | obbligatorio, intero 1–`BOOKING_MAX_PARTY_SIZE` |
| `first_name`, `last_name` | obbligatori, max 100 caratteri |
| `phone` | obbligatorio, max 30; formati: `333 123 4567`, `+39 333…`, `0039 333…`, fissi `06…`, internazionali `+44…` |
| `email` | facoltativa, email valida |
| `notes` | facoltative, max 500 caratteri |

Il tavolo viene assegnato dal backend: un eventuale `restaurant_table_id` nel payload viene ignorato.

Risposta **201**:

```json
{
  "data": {
    "reference": "0afd8af6-ba21-49eb-b53a-f61ceb0d4105",
    "status": "confirmed",
    "date": "2026-10-17",
    "time": "20:30",
    "party_size": 6,
    "starts_at": "2026-10-17T20:30:00+02:00",
    "ends_at": "2026-10-17T21:00:00+02:00",
    "first_name": "Mario",
    "last_name": "Rossi",
    "phone": "3331234567",
    "email": "mario@example.com",
    "notes": "Preferibilmente zona tranquilla",
    "cancelled_at": null,
    "cancellation_token": "hS3IfT912aMyhYNryf9dAB7OvHjJikixWeBp4fQZd9H0IR4c"
  }
}
```

`cancellation_token` compare **solo qui** e non è recuperabile in seguito. La PWA deve mostrarlo o
salvarlo, ad esempio in localStorage insieme a `reference`: senza token la prenotazione si gestisce
solo tramite il ristorante.

Errori: `422 VALIDATION_FAILED`, `422 PARTY_SIZE_NOT_SUPPORTED`, `422 DATE_OUT_OF_RANGE`,
`422 BOOKING_TOO_SOON`, `409 SLOT_NOT_AVAILABLE`, `409 DUPLICATE_RESERVATION`,
`409 PHONE_DAILY_LIMIT_REACHED`, `429 TOO_MANY_REQUESTS`.

### GET /reservations/{reference}

Header obbligatorio: `X-Reservation-Token: <token>`.
Risposta 200 con lo stesso schema di creazione, senza `cancellation_token`.

- Token mancante: `401 RESERVATION_TOKEN_MISSING`.
- Token errato o riferimento inesistente: `404 RESERVATION_NOT_FOUND`. La risposta è identica nei
  due casi e non rivela se la prenotazione esiste.

### POST /reservations/{reference}/cancel

Header obbligatorio: `X-Reservation-Token: <token>`. Nessun corpo.
Risposta 200 con la prenotazione (`status: "cancelled"`, `cancelled_at` valorizzato).
L'operazione è idempotente: ripeterla restituisce 200 senza altri effetti.
Gli errori sono gli stessi dell'endpoint precedente.

## Errori

```json
{ "error": { "code": "SLOT_NOT_AVAILABLE", "message": "L'orario selezionato non è più disponibile." } }
```

La validazione aggiunge `fields`:

```json
{ "error": { "code": "VALIDATION_FAILED", "message": "I dati inviati non sono validi.",
  "fields": { "phone": ["Il numero di telefono non è valido."] } } }
```

| HTTP | `code` | Significato |
|---|---|---|
| 401 | `RESERVATION_TOKEN_MISSING` | header `X-Reservation-Token` assente |
| 404 | `RESERVATION_NOT_FOUND` | riferimento inesistente o token errato |
| 404 | `NOT_FOUND` | endpoint inesistente |
| 405 | `METHOD_NOT_ALLOWED` | metodo HTTP errato |
| 409 | `SLOT_NOT_AVAILABLE` | nessun tavolo compatibile libero in quell'orario (anche se occupato nel frattempo) |
| 409 | `DUPLICATE_RESERVATION` | prenotazione identica già confermata per lo stesso telefono |
| 409 | `PHONE_DAILY_LIMIT_REACHED` | limite giornaliero per telefono raggiunto |
| 422 | `VALIDATION_FAILED` | dati non validi (`fields` contiene i dettagli) |
| 422 | `PARTY_SIZE_NOT_SUPPORTED` | nessun tavolo attivo accetta quel numero di persone |
| 422 | `DATE_OUT_OF_RANGE` | data passata o oltre l'anticipo massimo |
| 422 | `MONTH_OUT_OF_RANGE` | mese del calendario precedente al corrente o troppo lontano |
| 422 | `BOOKING_TOO_SOON` | orario passato o entro l'anticipo minimo |
| 429 | `TOO_MANY_REQUESTS` | rate limit superato (vedi header `Retry-After`) |
| 500 | `SERVER_ERROR` | errore imprevisto; con `APP_DEBUG=false` non contiene dettagli |

## Esempi curl

```bash
B=http://127.0.0.1:8000/api/v1

curl -s $B/booking-settings -H "Accept: application/json"

curl -s "$B/availability?date=2026-10-20&party_size=2" -H "Accept: application/json"

curl -s -X POST $B/reservations -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"date":"2026-10-20","time":"20:30","party_size":2,"first_name":"Mario","last_name":"Rossi","phone":"333 123 4567"}'

curl -s $B/reservations/<REFERENCE> -H "Accept: application/json" -H "X-Reservation-Token: <TOKEN>"

curl -s -X POST $B/reservations/<REFERENCE>/cancel -H "Accept: application/json" -H "X-Reservation-Token: <TOKEN>"
```

In PowerShell usare `curl.exe` (non l'alias `curl`) oppure `Invoke-RestMethod`.
