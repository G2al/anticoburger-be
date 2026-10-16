# Architettura – Antico Burger backend

Backend Laravel 12 + Filament 5 per la prenotazione dei tavoli di un singolo ristorante.
Espone un pannello amministrativo (`/admin`) e un'API REST pubblica (`/api/v1`) per la futura PWA Next.js.

## Struttura

```
app/
  Console/Commands/GrantAdminCommand.php   app:grant-admin
  Enums/                                   ReservationStatus, ReservationChannel, BlockScope, BookingError
  Exceptions/BookingException.php          violazione di una regola di prenotazione (codice + HTTP status)
  Filament/
    Pages/                                 Dashboard, BookingSettingsPage
    Resources/                             Reservations, RestaurantTables, AvailabilityBlocks
    Support/BookingFeedback.php            converte gli errori dei Services in notifiche/errori di form
    Widgets/                               BookingStatsOverview, UpcomingReservations
  Http/
    Controllers/Api/V1/                    controller sottili
    Requests/Api/V1/                       validazione HTTP
    Resources/Api/V1/                      forma delle risposte JSON
  Models/                                  RestaurantTable, TableAllowedPartySize, TableSchedule,
                                           AvailabilityBlock, BookingSettings, Reservation, User
  Policies/                                autorizzazioni (solo amministratori)
  Services/Reservations/                   tutta la logica di dominio
  Support/BookingTime.php                  conversioni ora locale <-> UTC
config/booking.php                         fuso orario, limiti, rate limit
config/cors.php                            origin autorizzati
```

Filament e API usano **gli stessi Services**: le Resource non salvano mai i modelli direttamente
(`handleRecordCreation` / `handleRecordUpdate` / azioni delegano ai Services).

## Modello dati

| Tabella | Contenuto |
|---|---|
| `restaurant_tables` | nome univoco, `duration_minutes`, `slot_interval_minutes`, `turnaround_minutes`, `assignment_priority`, `is_active` |
| `table_allowed_party_sizes` | capienze ammesse esplicite (`unique(restaurant_table_id, party_size)`) – es. 2, 4, 6 |
| `table_schedules` | fasce settimanali: `day_of_week` (1 = lunedì … 7 = domenica), `first_booking_time`, `last_booking_time` (entrambi **orari di inizio inclusi**) |
| `availability_blocks` | blocchi `restaurant`/`table`, intervallo di date, intera giornata o fascia oraria, `is_active` |
| `booking_settings` | riga singleton (`id = 1`) con le impostazioni globali |
| `reservations` | prenotazioni; `starts_at`, `ends_at`, `occupied_until` in UTC; `public_reference` UUID v4; `cancellation_token_hash` (SHA-256) |
| `users.is_admin` | unico ruolo applicativo |

Relazioni: un tavolo ha molte capienze, fasce, prenotazioni e blocchi. Capienze, fasce e blocchi
del tavolo vengono eliminati a cascata con il tavolo; le prenotazioni no (`restrictOnDelete`):
un tavolo con storico non può essere eliminato, va disattivato.

Indici su prenotazioni: `public_reference` (unique), `(restaurant_table_id, starts_at)`,
`(phone_normalized, starts_at)`, `(status, starts_at)`.

## Services

| Classe | Responsabilità |
|---|---|
| `AvailabilityService` | genera gli orari, verifica blocchi e conflitti, ordina i tavoli (best-fit), finestra di prenotazione pubblica |
| `ReservationService` | creazione pubblica/admin, modifica, spostamento, annullamento, accesso cliente con token |
| `RestaurantTableService` | creazione/modifica tavolo con capienze e fasce, attivazione, eliminazione protetta |
| `AvailabilityBlockService` | CRUD dei blocchi e prenotazioni coinvolte |
| `BookingSettingsService` | aggiornamento validato del singleton |
| `BookingLock` | transazione + lock esclusivo condiviso da tutte le operazioni che toccano l'occupazione |
| `PhoneNumberNormalizer` | normalizzazione E.164 |

## Regole di disponibilità

1. Si considerano solo i tavoli **attivi** con la **capienza esatta** richiesta.
2. Per ogni tavolo si prendono le fasce del giorno (`dayOfWeekIso` della data locale) e si generano
   gli orari `first, first + intervallo, …, ≤ last`. L'ultimo orario della fascia è incluso anche
   se la prenotazione finisce dopo. Gli orari di più fasce vengono deduplicati.
3. L'occupazione di un candidato è `[start, start + durata + riassetto)`.
4. Il candidato è libero se l'occupazione non si sovrappone a:
   - un blocco **attivo** del ristorante o di quel tavolo;
   - una prenotazione **confermata** dello stesso tavolo.

   Sovrapposizione: `a.start < b.end AND a.end > b.start` (intervalli semiaperti; prenotazioni
   consecutive non sono in conflitto).
5. Uno slot pubblico è disponibile se almeno un tavolo compatibile è libero. Ogni tavolo è valutato
   con la propria durata, intervallo e riassetto.
6. Per le prenotazioni pubbliche valgono anche: data non passata, `max_advance_days` (giorni di
   calendario, 0 = illimitato) e `min_lead_minutes` (0 = nessun anticipo, ma mai orari passati).

Il calendario mensile (`AvailabilityService::publicCalendarFor`) applica le stesse regole a tutti i
giorni del mese con un numero fisso di query: tavoli, blocchi e prenotazioni del mese vengono
caricati una sola volta. Un giorno è `available` esattamente quando `publicSlotsFor` restituisce
almeno uno slot (verificato da un test). Gli altri stati sono:
- `closed`: nessuna fascia, oppure tutti gli orari bloccati;
- `not_bookable`: fuori dalla finestra di prenotazione;
- `fully_booked`: tutti gli orari non bloccati sono già prenotati.

Gli slot non sono salvati nel database. L'API di disponibilità è solo informativa: alla conferma
tutti i controlli vengono ripetuti dentro il lock.

Le occupazioni possono superare la mezzanotte: blocchi e prenotazioni vengono caricati fino alla
fine del giorno successivo. Le fasce di inizio e le fasce dei blocchi appartengono invece a un solo
giorno (per i blocchi notturni si usano due blocchi).

Scelta progettuale: **il controllo dei blocchi usa l'occupazione completa (incluso il riassetto)**,
come il controllo dei conflitti. Un'unica regola di occupazione rende il comportamento prevedibile.

## Assegnazione best-fit

Tra i tavoli liberi si sceglie il primo secondo, in ordine:

1. capienza compatibile e disponibilità (filtri);
2. minore capienza massima ammessa;
3. minor numero di capienze supportate;
4. `assignment_priority` più bassa;
5. `id` più basso.

Se il tavolo migliore è occupato si passa al successivo. L'admin può scegliere un tavolo
specifico, che deve comunque essere compatibile e libero. Non è prevista l'unione di tavoli.

## Transazioni e locking

`BookingLock::run()` apre una transazione (`DB::transaction`, 3 tentativi in caso di deadlock) e,
come **prima istruzione**, esegue `SELECT … FOR UPDATE` sulla riga singleton di `booking_settings`.
Dentro il lock vengono riletti i dati, ripetuti tutti i controlli (finestra, duplicati, limite
telefono, disponibilità), scelto il tavolo e salvata la prenotazione.

Il lock serializza tutte le operazioni che cambiano occupazione o disponibilità: prenotazioni
pubbliche e manuali, modifica/spostamento/annullamento, modifica dei tavoli, blocchi e impostazioni.
Per un singolo ristorante la serializzazione dura pochi millisecondi ed elimina ogni race condition,
incluso il superamento concorrente del limite per telefono.

Il lock è la prima lettura della transazione: con InnoDB in REPEATABLE READ lo snapshot nasce dopo
l'acquisizione, quindi ogni lettura successiva vede i dati committati dal detentore precedente.

Verificato con `tests/Concurrency/ConcurrentBookingTest.php`: 8 processi PHP separati sull'ultimo
tavolo producono 1 sola prenotazione. Con il lock disattivato lo stesso test ne produce 8.

## Fuso orario

- `config('app.timezone')` resta `UTC`: tutti gli istanti sono salvati in UTC.
- Orari inseriti da clienti e admin, fasce e blocchi sono interpretati in `Europe/Rome`
  (`config('booking.timezone')`), tramite `App\Support\BookingTime`.
- Durate e conflitti sono calcolati su istanti reali, quindi i cambi di ora legale sono gestiti
  (es. il 25/10/2026 dura 25 ore).
- L'API restituisce date/ore locali e istanti ISO 8601 con offset (`+02:00` / `+01:00`).
- Filament mostra gli istanti esplicitamente nel fuso del ristorante.

## Blocchi

- Ambito `restaurant` (`restaurant_table_id = NULL`) o `table` (tavolo obbligatorio).
- Intera giornata: dalle 00:00 locali alle 00:00 del giorno dopo, per ogni data inclusa.
- Fascia oraria: la stessa fascia applicata a ogni data (fine > inizio, nessun attraversamento
  della mezzanotte).
- I blocchi prevalgono sulle fasce ordinarie e non modificano mai le prenotazioni esistenti.
  Alla creazione, modifica o riattivazione l'admin riceve un avviso con il numero di prenotazioni
  coinvolte e il link all'elenco filtrato.

## Limiti e anti-abuso

| Impostazione | Default | 0 significa |
|---|---|---|
| `min_lead_minutes` | 30 | nessun anticipo minimo |
| `max_advance_days` | 30 | nessun limite |
| `max_reservations_per_phone_per_day` | 2 | nessun limite |
| `prevent_duplicate_bookings` | true | – |

- Il limite per telefono conta le prenotazioni **confermate** con lo stesso `phone_normalized`
  che **iniziano nello stesso giorno locale**. Le annullate non contano.
- Duplicato = stesso telefono normalizzato, stesso istante di inizio, stesso numero di persone,
  stato confermato.
- Questi limiti si applicano solo alle prenotazioni pubbliche. L'admin deve sempre rispettare
  capienze, fasce, blocchi, tavoli attivi e conflitti.
- Rate limit Laravel: letture per IP; creazione per numero di telefono (gli utenti dietro lo stesso
  IP non si bloccano a vicenda) più un tetto per IP; endpoint con token per IP.

## Telefono

`PhoneNumberNormalizer` produce un numero E.164: spazi, trattini, punti e parentesi vengono
rimossi e `00` diventa `+`. Senza prefisso il numero è considerato italiano (6–11 cifre) e mantiene
lo zero iniziale dei fissi (`06 1234567` → `+39061234567`). I numeri internazionali richiedono un
prefisso esplicito (8–15 cifre). Il numero originale resta in `phone`. Non c'è vincolo di unicità.

## Token di annullamento

- Generato con `Str::random(48)` (CSPRNG) solo per le prenotazioni pubbliche.
- Restituito solo nella risposta di creazione. Nel database c'è solo `sha256(token)`, confrontato
  con `hash_equals`.
- Riferimento sconosciuto e token errato restituiscono la stessa risposta 404: l'esistenza della
  prenotazione non viene rivelata.
- Le prenotazioni create dall'admin non hanno token: si gestiscono dal ristorante.

## Modifiche dell'admin

- La modifica dei soli dati cliente non tocca tavolo, orari o occupazione, anche se nel frattempo
  il tavolo ha cambiato durata o è stato aggiunto un blocco.
- La modifica di data, ora, persone o tavolo ricontrolla tutto, ignorando la prenotazione stessa,
  e ricalcola `ends_at`/`occupied_until` con la configurazione attuale del tavolo.
- Il campo tavolo vuoto in modifica richiede una nuova assegnazione automatica.
- Le prenotazioni annullate restano modificabili solo nei dati cliente.
- Due modi per togliere una prenotazione:
  - **Annulla**: resta nello storico con stato `cancelled`;
  - **Elimina definitivamente** (singola o di massa, solo admin, con conferma): cancella il record
    dal database in modo irreversibile, passando da `ReservationService::delete` sotto `BookingLock`.
- In entrambi i casi il tavolo torna libero. Un tavolo senza più prenotazioni può essere eliminato.

## Sicurezza del pannello

- `User` implementa `FilamentUser`; `canAccessPanel()` richiede `is_admin = true`.
- `is_admin` non è assegnabile in massa e si concede solo con `php artisan app:grant-admin`.
- Policies su tutte le Resource; la pagina impostazioni verifica `canAccess()`.
- I toggle inline sono `disabled()` lato server se l'utente non ha il permesso `update`:
  Filament controlla `disabled()` prima di salvare.
- La registrazione pubblica non è abilitata.
