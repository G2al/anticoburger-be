# ANTICO BURGER — SVILUPPO PWA PRENOTAZIONI (FRONTEND)

Sei un senior frontend engineer specializzato in Next.js (App Router), React, TypeScript, Tailwind CSS,
shadcn/ui, design di interfacce mobile e Progressive Web App.

Devi sviluppare **realmente e completamente** la PWA con cui i clienti di "Antico Burger" prenotano un
tavolo. Scrivi il codice, configura il progetto, collega le API reali, testa e correggi fino a
consegnare un frontend funzionante e pronto alla pubblicazione. Non limitarti a un piano.

Lavora in autonomia, fase dopo fase, senza fermarti a chiedere conferma. Fermati solo davanti a una
decisione che non puoi prendere da solo e che cambia il risultato.

---

## 1. Contesto e vincoli

- Progetto frontend già inizializzato in `C:\Users\giomm\Desktop\project\anticoburger-fe`.
  **Non reinizializzarlo**, non ricrearlo, non cambiare package manager (npm, c'è `package-lock.json`).
- Ambiente: Windows + PowerShell + VS Code, Node 24.
- Il backend Laravel 12 + Filament 5 esiste già (`anticoburger-be`) ed è completo e testato.
  **Non modificarlo.** Il contratto API completo è nell'**Allegato A** di questo prompt: è l'unica
  fonte di verità. Non inventare endpoint, parametri, campi o comportamenti. Se ti serve qualcosa
  che non c'è, segnalalo nel report finale invece di simularlo.
- Il frontend serve **solo** a prenotare e ad annullare una prenotazione. Non sviluppare login,
  account, registrazione, pagamenti, menu, e-commerce, chat, notifiche email/SMS/WhatsApp/push,
  né scelta del tavolo.
- Tutta l'interfaccia, i messaggi, le validazioni e le conferme sono **esclusivamente in italiano**.

### Stato attuale del frontend (verificato in sola lettura)

| Pacchetto | Versione installata |
|---|---|
| next | 16.4.2 (App Router, `src/app`) |
| react / react-dom | 19.3.0 |
| typescript | 5.9.3 (strict) |
| tailwindcss | 4.3.3 (via `@tailwindcss/turbopack`, CSS-first in `src/app/globals.css`) |
| shadcn (CLI) | 4.22.0, `components.json` con `"style": "base-luma"` (preset **Luma**), `baseColor: neutral`, icone lucide, alias `@/components`, `@/components/ui`, `@/lib`, `@/hooks` |
| @base-ui/react | 1.9.0 (primitive dei componenti shadcn) |
| motion | 14.3.0 |
| lucide-react | 1.58.0 |
| cn | 0.5.0: pacchetto ufficiale shadcn che sostituisce `clsx` + `tailwind-merge`; `src/lib/utils.ts` riesporta `cn` da `"cn"` |
| tw-animate-css | 1.4.0 |
| eslint | 9 con `eslint-config-next` 16.4.2 (core-web-vitals + typescript) |

Altri dettagli verificati:
- `next.config.ts` abilita `cacheComponents: true` e `partialPrefetching: true`.
- `AGENTS.md` avverte che questa versione di Next.js ha API e convenzioni diverse da quelle che
  conosci. **Prima di scrivere codice leggi le guide in `node_modules/next/dist/docs/`**
  (cartelle `01-app`, `03-architecture`…), in particolare: Cache Components e Suspense, metadata,
  `manifest`, icone, font, route handlers, `"use client"`, error/loading/not-found.
  Rispetta le deprecazioni indicate.
- Esistono solo `src/app/layout.tsx`, `src/app/page.tsx` (pagina demo di create-next-app),
  `src/app/globals.css` (token neutri di default shadcn) e `src/components/ui/button.tsx`
  (Button shadcn Luma su Base UI).
- `layout.tsx` ha `lang="en"` e metadata di default ("Create Next App"). Carica sia Inter sia
  Geist; Inter è assegnato a `--font-sans`. Il font del progetto deve essere **Geist** (sans) e,
  se serve, Geist Mono: rimuovi Inter.
- Loghi ufficiali già presenti:
  - `public/images/logo-with-bgwhite-and-title-black.png`: logo nero su sfondo bianco;
  - `public/images/logo-with-bg-primarycolor-and-title-white.png`: logo bianco su sfondo bordeaux.
  Analizzali (dimensioni, colori esatti) e ricava da qui il colore bordeaux del brand.
- Non sono installati strumenti di test: aggiungili tu (vedi §10).
- In `public/` ci sono gli SVG demo di create-next-app (`next.svg`, `vercel.svg`, `file.svg`,
  `globe.svg`, `window.svg`): rimuovili se non usati.

Prima di iniziare **verifica tu stesso** le versioni (`npm ls --depth=0`), leggi i file esistenti e
controlla con la CLI di shadcn quali componenti Luma sono disponibili
(`npx shadcn@latest add <componente>`). Aggiungi i componenti shadcn con la CLI invece di
scriverli da zero.

---

## 2. Brand, UI e UX — priorità altissima

### Identità visiva
- Stile **elegante, raffinato, premium, minimale**. Assolutamente non "fast food": niente arancioni
  e gialli saturi.
- Palette: **bordeaux scuro** (colore del logo) come primario, **bianco**, **crema** come superficie
  calda, testi quasi neri caldi, grigi caldi per i secondari, un accento sobrio (es. un ottone/oro
  desaturato, usato con parsimonia). Definisci tutto come token CSS (`--primary`, `--background`,
  `--card`, `--muted`, `--accent`, `--ring`, `--destructive`…) in `globals.css`, in formato
  `oklch`, sostituendo i token neutri di default. Verifica il contrasto WCAG AA del testo.
- Il tema chiaro è quello principale. Aggiungi il tema scuro solo se resta coerente e curato;
  altrimenti fissa il tema chiaro e il `color-scheme` corrispondente.
- Tipografia Geist con gerarchia chiara (titoli con tracking leggermente stretto, numeri tabellari
  per orari e date), spaziature generose, angoli arrotondati coerenti con Luma.
- Usa i loghi ufficiali. Non ridisegnarli e non alterarne colori o proporzioni.

### Esperienza
- **Mobile-first**, deve sembrare un'app nativa:
  - layout a colonna singola, larghezza massima contenuta su desktop (card centrata);
  - CTA principale in basso e raggiungibile con il pollice (barra azioni sticky);
  - aree touch di almeno 44×44 px;
  - `viewport-fit=cover` con padding `env(safe-area-inset-*)` per notch e home indicator iPhone;
  - unità `dvh`/`svh` invece di `vh`;
  - niente zoom automatico sugli input iOS (font-size ≥ 16px);
  - tastiere corrette: `inputMode="tel"`, `type="email"`, `autoComplete="given-name"`,
    `"family-name"`, `"tel"`, `"email"`, `enterKeyHint`;
  - nessuno scroll orizzontale; `overscroll-behavior` curato.
- Componenti shadcn/ui (Luma, su Base UI), personalizzati tramite token e varianti. Non introdurre
  un secondo design system né altre librerie di componenti.
- Feedback immediato a ogni interazione: stati pressed, focus visibile, disabled, loading
  sul pulsante, `aria-live` per gli errori.
- Accessibilità: semantica corretta, etichette, focus gestito al cambio step (focus sul titolo
  dello step), navigazione da tastiera, ruoli e `aria-*` per calendario e griglia orari,
  messaggi di errore associati ai campi.

### Animazioni (Motion 14)
- Transizioni morbide tra gli step (slide + fade direzionale avanti/indietro con `AnimatePresence`),
  entrata e uscita degli elementi, microinterazioni su pulsanti e chip (scale leggero al tap),
  selezione animata di giorno e orario (indicatore condiviso con `layoutId`), schermata di
  conferma elegante (es. check animato), apparizione coordinata delle liste (stagger breve).
- Durate brevi (indicativamente 150–300 ms) con spring morbide. Anima solo `transform` e `opacity`.
- Rispetta `prefers-reduced-motion` (`MotionConfig reducedMotion="user"` o `useReducedMotion`).
- Usa `LazyMotion` + `domAnimation` con il componente `m` per contenere il bundle.
- Niente animazioni decorative, lente o che ritardano l'azione dell'utente.

### Caricamenti
- Skeleton con le stesse dimensioni del contenuto finale (calendario, griglia orari): zero
  layout shift.
- Nessun flash di stati vuoti prima del caricamento; nessuno spinner a schermo intero se evitabile.
- Stati loading, empty, error e success progettati per ogni schermata.
- Prefetch intelligente: quando l'utente sceglie il numero di persone, carica il calendario del
  mese; quando tocca un giorno, carica gli slot.

---

## 3. Flusso e schermate

Rotte (App Router):

| Rotta | Contenuto |
|---|---|
| `/` | Home: logo, breve testo di benvenuto, CTA "Prenota un tavolo", accesso a "Le mie prenotazioni" se presenti sul dispositivo |
| `/prenota` | Flusso di prenotazione a step |
| `/prenotazione` | Gestione prenotazione: legge `reference` e `token` dal **frammento** dell'URL (`#ref=…&token=…`) o dall'archivio locale, mostra il dettaglio e permette l'annullamento |
| `/prenotazioni` | "Le mie prenotazioni" salvate su questo dispositivo (opzionale ma consigliato) |
| `/offline` | Fallback offline |

Le informazioni del ristorante (indirizzo, telefono, orari indicativi) **non esistono nelle API**:
se le mostri, mettile in un unico file di configurazione statica (`src/config/restaurant.ts`) con
valori segnaposto chiaramente marcati da sostituire.

### Step del flusso `/prenota`
1. **Persone**: chip o selettore con **solo** i valori di `party_sizes` da `GET /booking-settings`,
   che possono non essere consecutivi. Se `party_sizes` è vuoto, mostra lo stato "Al momento non è
   possibile prenotare online" con il contatto del ristorante.
2. **Data**: calendario mensile da `GET /calendar`. Giorni `available` selezionabili; `fully_booked`
   ("Completo"), `closed` ("Chiuso") e `not_bookable` non selezionabili e distinti visivamente.
   Navigazione tra mesi limitata: dal mese corrente al mese di `oggi + max_advance_days`
   (se `max_advance_days > 0`), comunque al massimo 12 mesi. Se nel mese non ci sono giorni
   disponibili, mostralo e proponi il mese successivo. Su mobile valuta anche una striscia
   orizzontale dei prossimi giorni come scorciatoia, ma i dati devono venire dal calendario.
3. **Orario**: griglia degli `slots` da `GET /availability`. Raggruppa visivamente per fascia
   (es. pranzo/cena in base all'ora) solo come presentazione. Se `slots` è vuoto (il calendario
   può essere cambiato nel frattempo), mostra lo stato vuoto e torna alla scelta della data.
   Non mostrare l'orario di fine: non è noto prima della conferma.
4. **Dati**: nome, cognome, telefono (obbligatori), email e note (facoltative), con validazione
   client allineata al backend (Allegato A §6.4) e messaggi in italiano. Riepilogo della scelta
   sempre visibile (persone, data estesa in italiano, ora) con possibilità di tornare indietro.
   Informativa breve: i dati servono solo a gestire la prenotazione.
5. **Conferma**: `POST /reservations`. Pulsante in stato di caricamento, doppio invio impedito.
   La UI di successo compare **solo** dopo il `201`.
6. **Prenotazione confermata**:
   - riepilogo (data, ora, persone, nome);
   - orario di fine prevista da `ends_at`;
   - riferimento breve leggibile (prime 8 cifre di `reference`, maiuscole);
   - spiegazione chiara del codice di gestione;
   - azioni: "Copia link di gestione", "Condividi" (Web Share API se disponibile),
     "Aggiungi al calendario" (file `.ics` generato client-side con `starts_at`/`ends_at`, senza token);
   - avviso: senza il link o questo dispositivo, l'annullamento si fa contattando il ristorante.

Il flusso sopravvive al **ricaricamento della pagina**:
- salva lo stato degli step (persone, data, ora, dati anagrafici) in `sessionStorage` e/o nei
  query param non sensibili (`?persone=2&data=2026-10-20`);
- al ripristino **rivalida sempre** con il backend: data e orario possono non essere più
  disponibili;
- dopo la conferma, un reload della pagina di successo mostra la prenotazione dall'archivio
  locale e non ripete la POST.

### Gestione e annullamento (`/prenotazione`)
- Legge `ref` e `token` da `location.hash` (lato client). Non metterli mai in query string, in path
  o nei log. Dopo la lettura può salvarli nell'archivio locale.
- `GET /reservations/{reference}` con header `X-Reservation-Token`. Mostra il dettaglio e lo stato.
- Annullamento con dialog di conferma, poi `POST /reservations/{reference}/cancel`.
  Se lo stato era già `cancelled`, mostra "La prenotazione risulta già annullata" (la chiamata è
  idempotente). Dopo l'annullamento aggiorna l'archivio locale e invalida le cache di
  calendario e slot.
- `404 RESERVATION_NOT_FOUND`: "Prenotazione non trovata o link non valido. Per assistenza contatta
  il ristorante." Rimuovi la voce locale corrispondente solo dopo conferma dell'utente.
- Il link di gestione si costruisce così:
  `${origin}/prenotazione#ref=${reference}&token=${token}`.

### Archivio locale delle prenotazioni
- `localStorage`, chiave versionata (es. `ab:reservations:v1`), contenente per ogni prenotazione:
  `reference`, `token`, `date`, `time`, `party_size`, `first_name`, `status`, `savedAt`.
- Lettura e scrittura in `try/catch`: la navigazione privata può bloccare lo storage, e l'app
  deve funzionare comunque.
- Mostra le prenotazioni future; quelle passate possono essere nascoste o rimosse.
- Il token è un dato segreto: non loggarlo, non inviarlo ad analytics né a sistemi di error
  reporting, non metterlo in URL diversi dal frammento.

---

## 4. Architettura

Codice semplice, leggibile e parlante. Niente Redux, Zustand o altri state manager. Niente
astrazioni premature e niente componenti monolitici.

Struttura indicativa (adattala se trovi una soluzione più pulita, ma mantieni la separazione):

```
src/
  app/
    layout.tsx               html lang="it", font Geist, metadata, viewport, provider
    page.tsx                 home
    prenota/page.tsx
    prenotazione/page.tsx
    prenotazioni/page.tsx
    offline/page.tsx
    manifest.ts              Web App Manifest
    icon.png / apple-icon.png (o generati) 
    error.tsx, not-found.tsx, global-error.tsx
  components/
    ui/                      componenti shadcn (CLI)
    booking/                 PartySizeStep, DateStep (calendario), TimeStep, DetailsStep,
                             BookingSummary, ConfirmationView, StepTransition…
    reservation/             ReservationDetails, CancelReservationDialog
    layout/                  AppShell, Header, BottomActionBar, OfflineBanner
  lib/
    api/
      client.ts              fetch centralizzato: base URL, header, timeout, parsing, ApiError
      types.ts               tipi dell'Allegato A
      endpoints.ts           funzioni per ogni endpoint
      errors.ts              mappa error.code → messaggio italiano e azione UI
    date.ts                  utilità data/ora in Europe/Rome (solo Intl, nessuna libreria)
    phone.ts                 normalizzazione/validazione telefono identica al backend
    reservation-storage.ts   archivio locale
    validation.ts            schemi Zod dei form
  hooks/
    use-booking-settings.ts, use-calendar.ts, use-availability.ts,
    use-create-reservation.ts, use-reservation.ts, use-cancel-reservation.ts, use-online-status.ts
  config/
    restaurant.ts            dati statici del ristorante (segnaposto)
```

Linee guida:
- **Server vs client**: le pagine e il layout restano Server Components. Il flusso di prenotazione e
  la gestione sono Client Components isolati (`"use client"` al livello più basso possibile).
  Con `cacheComponents: true` rispetta le regole su dati dinamici e Suspense descritte nella
  documentazione locale di Next. I dati di disponibilità **non** vanno cachati lato server né
  prerenderizzati: si caricano nel browser.
- **Client HTTP unico** (`lib/api/client.ts`):
  - `fetch` con `credentials: "omit"`, header `Accept: application/json` (e `Content-Type` per
    le POST), timeout con `AbortController` (es. 10 s; 15 s per la POST);
  - trasforma ogni risposta non 2xx in un `ApiError` tipizzato (`status`, `code`, `message`,
    `fields`, `retryAfter`), gestendo anche corpi non JSON (HTML di proxy, errori di rete,
    CORS, timeout) con codici client come `NETWORK_ERROR`, `TIMEOUT`, `INVALID_RESPONSE`;
  - non logga mai gli header né il token.
- **Dipendenze consigliate** (installa solo ciò che usi davvero e verifica la compatibilità con
  React 19.3 e Next 16):
  - `@tanstack/react-query` per cache, stati asincroni, invalidazione dopo 409 e cancel;
  - `react-hook-form` + `zod` + `@hookform/resolvers` per i form;
  - nient'altro senza una ragione concreta.
  - Niente librerie di date: usa `Intl` con `timeZone: "Europe/Rome"`.
- Configurazione TanStack Query:
  - `booking-settings`: `staleTime` di qualche minuto;
  - `calendar` e `availability`: `staleTime` breve (≈30–60 s), `refetchOnWindowFocus: true`,
    `retry` limitato e mai sui 4xx;
  - mutazioni **senza retry**;
  - dopo `201`, `409` o annullamento: `invalidateQueries` di calendario e disponibilità;
  - non persistere la cache di queste query.
- Variabili d'ambiente: `NEXT_PUBLIC_API_BASE_URL` (es. `http://127.0.0.1:8000/api/v1`, senza
  slash finale). Valida la presenza all'avvio con un messaggio chiaro. Crea `.env.example` e
  `.env.local` (quest'ultimo ignorato da git). In produzione:
  `https://admin.anticoburger.it/api/v1`.

---

## 5. Messaggi d'errore e scenari obbligatori

Basati su `error.code`, mai sul testo. Il testo del backend è un fallback. Tutti i messaggi sono
in italiano, brevi e con l'azione successiva chiara.

| Scenario | Come si presenta | Comportamento richiesto |
|---|---|---|
| Nessun tavolo configurato | `party_sizes: []` | stato "Prenotazioni online non disponibili" + contatti |
| Numero persone non supportato | non selezionabile; se arriva `PARTY_SIZE_NOT_SUPPORTED` | ricarica settings, torna allo step persone |
| Giorno di chiusura | calendario `closed` | giorno non selezionabile, etichetta "Chiuso" |
| Giorno completo | calendario `fully_booked` | non selezionabile, "Completo"; suggerisci altri giorni |
| Data non prenotabile | `not_bookable` o `DATE_OUT_OF_RANGE` | non selezionabile; con 422 torna alla scelta della data |
| Mese fuori intervallo | `MONTH_OUT_OF_RANGE` | non deve accadere se la navigazione è limitata; gestiscilo comunque |
| Nessuna disponibilità | `slots: []` | stato vuoto con invito a scegliere un altro giorno |
| Orario troppo vicino | `BOOKING_TOO_SOON` | ricarica gli slot, chiedi un altro orario |
| Slot esaurito durante la compilazione | `409 SLOT_NOT_AVAILABLE` | messaggio "Questo orario è appena stato prenotato", ricarica slot e calendario, torna all'orario **conservando i dati** |
| Prenotazione duplicata | `409 DUPLICATE_RESERVATION` | "Hai già una prenotazione identica"; se è sul dispositivo, link alla gestione |
| Limite prenotazioni | `409 PHONE_DAILY_LIMIT_REACHED` | spiega il limite giornaliero; invita a contattare il ristorante |
| Telefono non valido | validazione client o `fields.phone` | errore sul campo |
| Dati incompleti | validazione client o `VALIDATION_FAILED` | errori sui campi (mappa `fields` sui campi del form) e focus sul primo |
| Errore di rete / offline | `NETWORK_ERROR`, `navigator.onLine === false` | banner offline, azioni disabilitate, "Riprova" per le letture |
| Timeout | `TIMEOUT` | letture: "Riprova". POST: esito incerto, **nessun retry automatico**, spiegazione + contatti |
| Backend non disponibile | 5xx / risposta non JSON | messaggio generico + "Riprova" |
| Troppe richieste | `429 TOO_MANY_REQUESTS` | "Troppe richieste, riprova tra N secondi" usando `Retry-After` |
| Prenotazione confermata | `201` | schermata di successo e salvataggio locale |
| Annullamento riuscito | `200`, `status: "cancelled"` | conferma visiva, aggiornamento archivio e cache |
| Token non valido | `404 RESERVATION_NOT_FOUND` | "Link non valido o prenotazione non trovata" |
| Token mancante | `401 RESERVATION_TOKEN_MISSING` | "Link incompleto" |
| Prenotazione già annullata | `GET` con `status: "cancelled"` o cancel ripetuto | stato "Già annullata", nessun pulsante di annullamento |
| Reload durante il flusso | – | ripristino dello stato + rivalidazione con il backend |

---

## 6. Integrazione con Laravel — regole non negoziabili

1. Gli orari e la disponibilità vengono **solo** dalle API. Il frontend non genera slot.
2. Mai mostrare una prenotazione come confermata prima del `201`.
3. Mai inviare un id del tavolo. Mai mostrare dati amministrativi.
4. Non conservare la disponibilità come se fosse definitiva: cache breve e rivalidazione.
5. Nessuna prenotazione o annullamento offline. Nessuna coda di invio differito.
6. Date e ore sempre in Europe/Rome (vedi Allegato A §8): "oggi" calcolato con
   `Intl.DateTimeFormat("en-CA", { timeZone: "Europe/Rome" })`. Mai `new Date("YYYY-MM-DD")`.
7. Il token di annullamento è segreto: solo nel frammento dell'URL, in `localStorage` e nell'header
   `X-Reservation-Token`. Mai in log, analytics, query string o report di errori.
8. La POST non è idempotente: niente retry automatici. Pulsante disabilitato durante l'invio.
9. Verifica CORS reale dal browser contro il backend locale (origin `http://localhost:3000`).
   Se usi un'altra porta o provi da smartphone in LAN, segnala che quell'origin va aggiunto a
   `CORS_ALLOWED_ORIGINS` nel backend: non toccare tu il backend.

---

## 7. PWA reale e installabile

- **Manifest** tramite `src/app/manifest.ts` (convenzione Next):
  - `name`: "Antico Burger – Prenotazioni"; `short_name`: "Antico Burger"; `lang`: "it";
  - `start_url`: "/", `scope`: "/", `display`: "standalone";
  - `background_color` e `theme_color` coerenti con il brand;
  - icone 192×192 e 512×512, anche in versione `maskable` con area di sicurezza, ricavate dal
    logo ufficiale (bianco su bordeaux per le icone).
- **iOS**:
  - `apple-touch-icon` 180×180;
  - metadata `appleWebApp` (capable, title, `statusBarStyle`);
  - `viewport` con `viewportFit: "cover"` e `themeColor`;
  - eventuali splash screen solo se semplici da mantenere.
- **Icone**: genera i PNG dai loghi in `public/images` con uno script ripetibile (es. `sharp`
  come devDependency, o un'alternativa leggera) e documenta come rigenerarli. Non alterare il logo.
- **Service Worker**:
  - scegli la soluzione più semplice e compatibile con Next 16 e Turbopack. Prima leggi la guida
    PWA nella documentazione locale di Next (`node_modules/next/dist/docs`): se suggerisce un
    service worker manuale in `public/sw.js` registrato da un piccolo componente client, preferisci
    quella strada a plugin datati;
  - precache della shell e degli asset statici (`/_next/static/*` con cache-first, versionata);
    font e icone cache-first;
  - navigazioni network-first con fallback `/offline`;
  - **mai** mettere in cache le risposte di `/api/v1/*` (passano sempre dalla rete; offline → errore
    gestito dall'app);
  - **mai** mettere in cache POST, né `/prenotazione` con dati sensibili.
- **Aggiornamento**: quando c'è un nuovo service worker, mostra un toast "Nuova versione
  disponibile – Aggiorna" (skipWaiting su richiesta); pulisci le cache vecchie in `activate`.
- **Offline**: banner persistente quando la connessione manca; azioni di prenotazione e
  annullamento disabilitate con spiegazione; le letture riprovano al ritorno online.
- **Installazione**:
  - Android/Chrome: gestisci `beforeinstallprompt` con un invito discreto, non invasivo e non
    ripetuto;
  - iOS Safari non supporta il prompt: mostra istruzioni brevi ("Condividi → Aggiungi alla schermata
    Home") solo su iOS e non in modalità standalone.
  - Documenta i limiti reali verificati su iOS: storage che può essere eliminato, nessun
    `beforeinstallprompt`, comportamento del service worker.
- Il service worker in sviluppo può dare fastidio: registralo solo in produzione, o rendilo
  disattivabile.

---

## 8. Performance

- Server Components per tutto ciò che non è interattivo; client components piccoli e isolati.
- Bundle JS iniziale contenuto: `LazyMotion`, import dinamici per ciò che non serve subito
  (es. dialog di annullamento, generatore `.ics`, prompt di installazione).
  Controlla l'output di `next build`.
- Niente librerie pesanti (niente moment/dayjs/date-fns: c'è `Intl`; niente librerie di
  calendario: costruisci la griglia con componenti shadcn o con una griglia semplice e
  accessibile).
- `next/font` per Geist (`display: swap`, solo i subset necessari); `next/image` per il logo con
  dimensioni esplicite e `priority` solo above-the-fold.
- Evita render inutili: stato locale per step, selettori stabili, niente context globale che
  rerenderizza tutto.
- Animazioni solo `transform`/`opacity`, niente animazioni di layout costose sulle liste lunghe.
- Obiettivi Lighthouse mobile in build di produzione: Performance ≥ 90, Accessibility ≥ 95,
  Best Practices ≥ 95, PWA installabile; CLS ≈ 0, LCP < 2,5 s.

---

## 9. Fasi di lavoro

1. Analisi del progetto esistente, delle versioni e della documentazione locale di Next 16.
2. Design system: token brand, font, icone, componenti shadcn necessari, layout di base.
3. Livello API: tipi, client, endpoint, mappa errori, utilità di data e telefono, test unitari.
4. Flusso di prenotazione completo con tutti gli stati.
5. Conferma, archivio locale, gestione e annullamento.
6. PWA: manifest, icone, service worker, offline, aggiornamento, installazione.
7. Rifinitura di animazioni, accessibilità e responsive.
8. Test, build, Lighthouse, verifica reale contro il backend, correzioni.
9. Documentazione (`README.md` del frontend) e report finale.

---

## 10. Testing (obbligatorio, eseguito davvero)

Installa e configura:
- **Vitest** + **@testing-library/react** + **@testing-library/user-event** + **jsdom** per
  unit e component test;
- **MSW** per simulare le API seguendo esattamente l'Allegato A (stesse forme e stessi codici);
- **Playwright** per gli end-to-end (Chromium mobile e WebKit/iPhone, se installabile).

Test minimi:
- unit:
  - normalizzazione e validazione del telefono, con gli stessi casi del backend (Allegato A §6.4);
  - schemi Zod;
  - utilità di data in Europe/Rome, compresi il cambio d'ora del 25/10/2026 e la data "oggi"
    con dispositivo in un altro fuso;
  - mappa errori;
  - client HTTP (timeout, corpo non JSON, 429 con `Retry-After`);
  - archivio locale (storage bloccato);
- component e flusso (MSW):
  - selezione persone limitata a `party_sizes`;
  - stati del calendario;
  - slot vuoti;
  - skeleton durante il caricamento;
  - invio e successo solo dopo `201`;
  - `409 SLOT_NOT_AVAILABLE` con dati conservati e slot ricaricati;
  - `DUPLICATE_RESERVATION`, `PHONE_DAILY_LIMIT_REACHED`, `VALIDATION_FAILED` mappato sui campi;
  - errore di rete e timeout senza retry della POST;
  - annullamento riuscito, già annullata, token errato o mancante;
  - ripristino dopo reload;
- e2e:
  - flusso completo di prenotazione e annullamento con MSW o con route mock di Playwright;
  - **separatamente**, una suite contro il backend Laravel reale avviato sul database di test
    (`$env:DB_DATABASE='anticoburger_test'; php artisan serve --port=8010`, con
    `NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8010/api/v1`). Richiede un tavolo di prova
    configurato dal pannello `/admin` del backend: se non c'è, segnalalo e non dichiarare la
    suite eseguita;
  - viewport 360×740, 390×844, 768×1024, 1280×800: nessuno scroll orizzontale, CTA visibile,
    safe area.

Comandi da eseguire e riportare con l'esito reale:
- `npx tsc --noEmit`
- `npm run lint`
- `npm run test` (Vitest)
- `npm run build`
- e2e Playwright
- Lighthouse su `npm run start` (mobile)
- verifica manifest, service worker e offline (DevTools → Application)

Non dichiarare superato un test che non hai eseguito. Se qualcosa non è eseguibile nell'ambiente,
dillo e spiega perché.

---

## 11. Definition of done e report finale

Il lavoro è finito quando:
- tutte le schermate e tutti gli scenari della §5 sono implementati;
- la PWA è installabile;
- typecheck, lint, test e build passano;
- il flusso è stato provato contro il backend reale.

Report finale (in italiano):
1. Funzionalità e schermate realizzate.
2. Struttura del progetto e scelte architetturali.
3. Dipendenze aggiunte e perché.
4. Palette e token definiti.
5. Strategia PWA e limiti iOS/Android verificati.
6. Test eseguiti con numeri reali (superati/falliti/non eseguiti e motivo).
7. Risultati Lighthouse.
8. Problemi trovati e risolti.
9. Eventuali incongruenze con il contratto API (senza modificare il backend).
10. Istruzioni di avvio, build e deploy su `https://app.anticoburger.it`.

---

# ALLEGATO A — Contratto API reale del backend (fonte di verità)

Tutto ciò che segue è implementato e testato nel backend. Ciò che non è descritto qui non esiste.

## A.1 Ambienti

| | Frontend | API base URL |
|---|---|---|
| Sviluppo | `http://localhost:3000` | `http://127.0.0.1:8000/api/v1` |
| Test (DB separato) | `http://localhost:3000` | `http://127.0.0.1:8010/api/v1` |
| Produzione | `https://app.anticoburger.it` | `https://admin.anticoburger.it/api/v1` |

## A.2 Regole generali

- Stateless, nessuna autenticazione, nessun cookie. `fetch` con `credentials: "omit"`.
- Header da inviare sempre: `Accept: application/json`. Per le POST con corpo:
  `Content-Type: application/json`.
- Successo: `{ "data": … }`. Errore: `{ "error": { "code", "message", "fields"? } }`.
- Basarsi su `error.code`. I messaggi del backend sono già in italiano.
- Date `YYYY-MM-DD` e ore `HH:mm` sono **locali Europe/Rome**. Gli istanti sono ISO 8601 con offset
  (`+02:00` in estate, `+01:00` in inverno).
- Nessun id interno (tavoli, prenotazioni) è esposto o accettato.

## A.3 CORS

- Path `api/*`.
- Origin consentiti: variabile backend `CORS_ALLOWED_ORIGINS`. Default
  `http://localhost:3000,http://127.0.0.1:3000`; produzione `https://app.anticoburger.it`.
- Metodi `GET, POST, OPTIONS`. Header consentiti `Accept, Content-Type, X-Requested-With,
  X-Reservation-Token`. Header esposti `Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining`.
- Nessuna credenziale; preflight in cache per 3600 s.
- Origin non autorizzato: il browser blocca la risposta e `fetch` lancia un `TypeError`, come per
  un errore di rete.

## A.4 Rate limit (default, al minuto)

| Gruppo | Endpoint | Limite |
|---|---|---|
| Letture | `GET /booking-settings`, `GET /availability`, `GET /calendar` | 60 per IP, condiviso |
| Creazione | `POST /reservations` | 5 per telefono normalizzato + 20 per IP |
| Accesso | `GET /reservations/{reference}`, `POST /reservations/{reference}/cancel` | 30 per IP |

Superato il limite la risposta è `429`, `error.code = "TOO_MANY_REQUESTS"`, con header `Retry-After` (secondi).

## A.5 Tipi TypeScript

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
    fields?: Record<string, string[]>; // solo con VALIDATION_FAILED; chiavi = nomi dei campi della richiesta
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
  slots: string[];    // "HH:mm" orari di INIZIO, ordinati, senza duplicati
};

export type CreateReservationInput = {
  date: string;        // "YYYY-MM-DD"
  time: string;        // "HH:mm" – uno degli slot restituiti
  party_size: number;
  first_name: string;
  last_name: string;
  phone: string;
  email?: string | null;
  notes?: string | null;
};

export type ReservationStatus = "confirmed" | "cancelled";

export type Reservation = {
  reference: string;           // UUID v4 pubblico
  status: ReservationStatus;
  date: string;                // "YYYY-MM-DD" locale
  time: string;                // "HH:mm" locale
  party_size: number;
  starts_at: string;           // ISO 8601 con offset
  ends_at: string;             // ISO 8601 con offset, fine prevista (dipende dal tavolo assegnato)
  first_name: string;
  last_name: string;
  phone: string;               // come digitato dal cliente
  email: string | null;
  notes: string | null;
  cancelled_at: string | null; // ISO 8601 con offset
};

export type CreatedReservation = Reservation & {
  cancellation_token: string;  // 48 caratteri [A-Za-z0-9], SOLO nella risposta di creazione
};
```

## A.6 Endpoint

### `GET /booking-settings`

Nessun parametro. `200`, esempio reale:

```json
{"data":{"timezone":"Europe/Rome","min_lead_minutes":30,"max_advance_days":30,"min_party_size":2,"max_party_size":4,"party_sizes":[2,3,4]}}
```

Senza tavoli attivi: `min_party_size: null`, `max_party_size: null`, `party_sizes: []`.

- Proporre **solo** i valori di `party_sizes`: possono non essere consecutivi.
- `min`/`max` non implicano che i valori intermedi siano accettati.
- I valori possono cambiare in qualsiasi momento.

### `GET /calendar?month=YYYY-MM&party_size=N`

| Parametro | Regole |
|---|---|
| `month` | obbligatorio, `Y-m` |
| `party_size` | obbligatorio, intero 1–20 |

`200`, sempre con tutti i giorni del mese in ordine. Estratto reale:

```json
{"data":{"month":"2026-10","party_size":2,"days":[
 {"date":"2026-10-15","status":"not_bookable"},
 {"date":"2026-10-16","status":"available"},
 {"date":"2026-10-21","status":"closed"},
 {"date":"2026-10-23","status":"fully_booked"},
 {"date":"2026-10-26","status":"closed"},
 {"date":"2026-10-29","status":"available"}]}}
```

| status | Significato |
|---|---|
| `available` | almeno un orario libero. Equivale esattamente a "`/availability` restituisce almeno uno slot" |
| `fully_booked` | aperto ma tutto occupato per quel numero di persone |
| `closed` | giorno di chiusura, blocco/ferie, oppure nessun tavolo attivo per quel numero di persone |
| `not_bookable` | passato, oltre l'anticipo massimo, oppure oggi senza più orari utili dopo l'anticipo minimo |

- Mesi consultabili: dal mese corrente (Europe/Rome) fino a +12 mesi; fuori intervallo
  `422 MONTH_OUT_OF_RANGE`.
- Con `max_advance_days > 0` i giorni oltre `oggi + max_advance_days` sono `not_bookable`.
- Dato indicativo: la prenotazione ricontrolla tutto.
- Errori: `422 VALIDATION_FAILED`, `422 MONTH_OUT_OF_RANGE`, `429`.

### `GET /availability?date=YYYY-MM-DD&party_size=N`

| Parametro | Regole |
|---|---|
| `date` | obbligatorio, `Y-m-d` |
| `party_size` | obbligatorio, intero 1–20 |

`200`, esempio reale:

```json
{"data":{"date":"2026-10-20","party_size":2,"slots":["19:00","19:30","20:00","20:30","21:00","21:30","22:00","22:30","23:00"]}}
```

- Orari di **inizio**; l'ultimo della fascia è incluso. La fine non è nota prima della conferma.
- `slots: []` significa chiuso, completo o numero di persone non supportato.
- Esclusi gli orari passati o entro `min_lead_minutes`.
- Data passata o oltre `max_advance_days`: `422 DATE_OUT_OF_RANGE`.
- Dato informativo, non riserva nulla.
- Errori: `422 VALIDATION_FAILED`, `422 DATE_OUT_OF_RANGE`, `429`.

### `POST /reservations`

Corpo JSON:

| Campo | Obbligatorio | Regole |
|---|---|---|
| `date` | sì | `Y-m-d` |
| `time` | sì | `H:i` esatto (`"20:30"`; `"20:30:00"` rifiutato); se non è uno slot valido → `409 SLOT_NOT_AVAILABLE` |
| `party_size` | sì | intero 1–20 |
| `first_name` | sì | ≤ 100 caratteri, qualsiasi carattere (apostrofi, accenti, trattini ok) |
| `last_name` | sì | ≤ 100 caratteri |
| `phone` | sì | ≤ 30 caratteri e normalizzabile (sotto) |
| `email` | no | email valida ≤ 190, oppure `null`/assente |
| `notes` | no | ≤ 500 caratteri, oppure `null`/assente |

- Il backend rimuove gli spazi iniziali e finali e trasforma le stringhe vuote in `null`.
- Campi sconosciuti vengono ignorati.

Normalizzazione del telefono (replicala identica nel frontend per la validazione):
- rimuovere spazi, `-`, `.`, `/`, `(`, `)`;
- `00` iniziale diventa `+`;
- con `+39…`: le cifre dopo `39` seguono la regola italiana;
- con un altro `+XX…`: solo cifre, la prima diversa da 0, 8–15 cifre totali, risultato `+<cifre>`;
- senza `+`: numero italiano, solo cifre, 6–11 cifre, risultato `+39<cifre>` con lo zero iniziale
  dei fissi mantenuto.

Casi verificati dal backend:

| Input | Risultato |
|---|---|
| `333 123 4567`, `+39 3331234567`, `0039 3331234567`, `333-123.4567`, `(+39) 333 1234567` | `+393331234567` |
| `06 1234567`, `+39 06 1234567`, `0039 06 1234567` | `+39061234567` |
| `+44 20 7946 0958` | `+442079460958` |
| `0049 151 12345678` | `+4915112345678` |
| `""`, `abc`, `123`, `+0123456789`, `+12`, `333 abc 4567`, `+1234567890123456`, `333123456789` | non valido |

Ordine dei controlli:
1. validazione → `422 VALIDATION_FAILED`;
2. data fuori finestra → `422 DATE_OUT_OF_RANGE`; orario passato o entro l'anticipo minimo →
   `422 BOOKING_TOO_SOON`;
3. nessun tavolo attivo per quel numero di persone → `422 PARTY_SIZE_NOT_SUPPORTED`;
4. prenotazione confermata identica (stesso telefono normalizzato, data, ora, persone) →
   `409 DUPLICATE_RESERVATION`;
5. massimo di prenotazioni confermate per telefono nel giorno della prenotazione raggiunto (default 2;
   le annullate non contano) → `409 PHONE_DAILY_LIMIT_REACHED`;
6. nessun tavolo compatibile libero → `409 SLOT_NOT_AVAILABLE`.

I passi 2–6 avvengono in una transazione con lock: due richieste simultanee sull'ultimo tavolo
producono una sola prenotazione, e l'altra riceve `409 SLOT_NOT_AVAILABLE`.

`201`, esempio reale:

```json
{"data":{"reference":"0afd8af6-ba21-49eb-b53a-f61ceb0d4105","status":"confirmed","date":"2026-10-20","time":"20:30","party_size":2,"starts_at":"2026-10-20T20:30:00+02:00","ends_at":"2026-10-20T21:00:00+02:00","first_name":"Mario","last_name":"Rossi","phone":"333 123 4567","email":"mario@example.com","notes":"Zona tranquilla","cancelled_at":null,"cancellation_token":"hS3IfT912aMyhYNryf9dAB7OvHjJikixWeBp4fQZd9H0IR4c"}}
```

Errori reali:

```json
{"error":{"code":"SLOT_NOT_AVAILABLE","message":"L'orario selezionato non è più disponibile."}}
```

```json
{"error":{"code":"VALIDATION_FAILED","message":"I dati inviati non sono validi.","fields":{"date":["Il campo data deve essere nel formato Y-m-d."],"time":["Il campo ora è obbligatorio."],"party_size":["Il campo numero di persone è obbligatorio."],"first_name":["Il campo nome è obbligatorio."],"last_name":["Il campo cognome è obbligatorio."],"phone":["Il numero di telefono non è valido."]}}}
```

**La POST non è idempotente**: nessun retry automatico. Dopo un timeout l'esito è sconosciuto.

### `GET /reservations/{reference}`

Header obbligatorio: `X-Reservation-Token: <cancellation_token>`.

- `200`: un `Reservation` senza `cancellation_token`. Funziona anche per le prenotazioni annullate.
- `401 RESERVATION_TOKEN_MISSING`: header assente o vuoto.
- `404 RESERVATION_NOT_FOUND`: token errato, riferimento inesistente o non UUID.
  I casi sono indistinguibili per sicurezza.

### `POST /reservations/{reference}/cancel`

Header obbligatorio: `X-Reservation-Token`. Nessun corpo.

- `200`: il `Reservation` con `status: "cancelled"` e `cancelled_at` valorizzato.
- Idempotente: se già annullata risponde `200` con gli stessi dati.
- Nessun limite temporale.
- Errori: `401`, `404`, `429` come sopra.

Non esiste un endpoint per modificare una prenotazione: si annulla e si prenota di nuovo.

## A.7 Errori

| HTTP | code | Messaggio backend |
|---|---|---|
| 401 | `RESERVATION_TOKEN_MISSING` | Per accedere alla prenotazione è necessario il codice segreto (header X-Reservation-Token). |
| 404 | `RESERVATION_NOT_FOUND` | Prenotazione non trovata. |
| 404 | `NOT_FOUND` | Risorsa non trovata. |
| 405 | `METHOD_NOT_ALLOWED` | Metodo HTTP non consentito. |
| 409 | `SLOT_NOT_AVAILABLE` | L'orario selezionato non è più disponibile. |
| 409 | `DUPLICATE_RESERVATION` | Esiste già una prenotazione identica per questo numero di telefono. |
| 409 | `PHONE_DAILY_LIMIT_REACHED` | È stato raggiunto il numero massimo di prenotazioni per questo numero di telefono nella giornata. |
| 422 | `VALIDATION_FAILED` | I dati inviati non sono validi. (+ `fields`) |
| 422 | `PARTY_SIZE_NOT_SUPPORTED` | Non ci sono tavoli prenotabili per il numero di persone indicato. |
| 422 | `DATE_OUT_OF_RANGE` | La data selezionata non è prenotabile. |
| 422 | `MONTH_OUT_OF_RANGE` | Il mese selezionato non è consultabile. |
| 422 | `BOOKING_TOO_SOON` | L'orario selezionato è troppo vicino: scegli un orario successivo. |
| 429 | `TOO_MANY_REQUESTS` | Troppe richieste. Riprova tra poco. |
| 500 | `SERVER_ERROR` | Si è verificato un errore imprevisto. Riprova più tardi. |

- In sviluppo, con il backend in `APP_DEBUG=true`, un 500 restituisce il JSON standard di Laravel
  e non l'envelope `error`: il client deve trattare ogni corpo non conforme come errore generico.
- Possono arrivare anche risposte non JSON (HTML 502/503/504 da proxy), errori di rete e timeout.

## A.8 Fuso orario

- "Oggi" va calcolato in Europe/Rome:
  `new Intl.DateTimeFormat("en-CA", { timeZone: "Europe/Rome" }).format(new Date())` → `YYYY-MM-DD`.
- Mai `new Date("YYYY-MM-DD")`: è interpretata come UTC. Tratta le date come stringhe; per
  mostrarle usa `Intl.DateTimeFormat("it-IT", { timeZone: "Europe/Rome", weekday: "long", day:
  "numeric", month: "long" })` partendo da un istante costruito in modo sicuro (es. mezzogiorno UTC
  di quella data).
- Per mostrare la prenotazione usa `date` e `time`. `starts_at`/`ends_at` servono per la fine
  prevista e per il file `.ics`.

## A.9 Token di annullamento

- Solo per le prenotazioni create dalla PWA; 48 caratteri alfanumerici; restituito **solo**
  nel `201`. Nel database c'è solo l'hash: se viene perso non è recuperabile.
- Non esiste recupero tramite telefono o email. Le prenotazioni create dal ristorante non hanno
  token e non sono consultabili dalla PWA.
- Va trasmesso solo nell'header `X-Reservation-Token`. Link di gestione con i dati nel frammento:
  `/prenotazione#ref=<reference>&token=<token>`.

## A.10 Impostazioni modificabili dall'amministratore

| Impostazione | Default | Effetto sul frontend |
|---|---|---|
| `min_lead_minutes` | 30 | orari vicini esclusi; `BOOKING_TOO_SOON` |
| `max_advance_days` | 30 (0 = illimitato) | giorni oltre il limite `not_bookable`; `DATE_OUT_OF_RANGE` |
| `max_reservations_per_phone_per_day` | 2 (0 = illimitato) | `PHONE_DAILY_LIMIT_REACHED` |
| `prevent_duplicate_bookings` | attivo | `DUPLICATE_RESERVATION` |
| Tavoli, capienze, fasce, blocchi | – | cambiano `party_sizes`, calendario e slot in tempo reale |

## A.11 Cosa non esiste nel backend

- Login o account cliente.
- Ricerca prenotazioni per telefono o email.
- Modifica di una prenotazione.
- Informazioni sul ristorante (indirizzo, orari, contatti).
- Notifiche.
- Chiave di idempotenza per la POST.
- Scelta del tavolo.
- Endpoint di health JSON nell'API (esiste solo `/up` di Laravel, HTML, fuori da `/api`).

Inizia ora dalla fase 1.
