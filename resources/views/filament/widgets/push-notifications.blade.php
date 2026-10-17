<x-filament-widgets::widget>
    <x-filament::section
        heading="Notifiche push"
        description="Ricevi su questo dispositivo una notifica per ogni nuova prenotazione online."
        icon="heroicon-o-bell"
    >
        <div
            x-data="{
                state: 'loading',
                busy: false,
                feedback: null,
                async init() {
                    try {
                        this.state = await window.anticoBurgerPush.status()
                    } catch (error) {
                        this.state = 'off'
                    }
                },
                async run(action) {
                    this.busy = true
                    this.feedback = null

                    try {
                        if (action === 'test') {
                            const sent = await window.anticoBurgerPush.sendTest()
                            this.feedback = sent > 0
                                ? 'Notifica di prova inviata ai tuoi dispositivi (' + sent + ').'
                                : 'Nessun dispositivo ha ricevuto la notifica: riattiva le notifiche.'
                        } else {
                            this.state = await window.anticoBurgerPush[action]()
                        }
                    } catch (error) {
                        this.feedback = error.message
                    } finally {
                        this.busy = false
                    }
                },
            }"
            class="flex flex-col gap-4"
        >
            <p class="text-sm text-gray-600 dark:text-gray-300">
                <span x-show="state === 'loading'">Verifica in corso…</span>
                <span x-show="state === 'on'" x-cloak>
                    <strong class="text-success-600 dark:text-success-400">Attive</strong> su questo dispositivo.
                </span>
                <span x-show="state === 'off'" x-cloak>Non attive su questo dispositivo.</span>
                <span x-show="state === 'denied'" x-cloak>
                    Le notifiche sono bloccate per questo sito: abilitale dalle impostazioni del browser o del telefono, poi ricarica la pagina.
                </span>
                <span x-show="state === 'ios-install'" x-cloak>
                    Su iPhone le notifiche funzionano solo dall'app installata: in Safari tocca Condividi → «Aggiungi alla schermata Home», poi apri il gestionale dall'icona e attivale da qui (iOS 16.4 o successivo).
                </span>
                <span x-show="state === 'unsupported'" x-cloak>Questo browser non supporta le notifiche push.</span>
                <span x-show="state === 'not-configured'" x-cloak>
                    Le notifiche non sono ancora configurate sul server (chiavi VAPID mancanti).
                </span>
            </p>

            <div class="flex flex-wrap gap-3">
                <x-filament::button
                    x-show="state === 'off'"
                    x-cloak
                    x-on:click="run('enable')"
                    x-bind:disabled="busy"
                    icon="heroicon-o-bell-alert"
                >
                    Attiva notifiche
                </x-filament::button>

                <x-filament::button
                    x-show="state === 'on'"
                    x-cloak
                    x-on:click="run('test')"
                    x-bind:disabled="busy"
                    icon="heroicon-o-paper-airplane"
                >
                    Invia notifica di prova
                </x-filament::button>

                <x-filament::button
                    x-show="state === 'on'"
                    x-cloak
                    x-on:click="run('disable')"
                    x-bind:disabled="busy"
                    color="gray"
                    icon="heroicon-o-bell-slash"
                >
                    Disattiva su questo dispositivo
                </x-filament::button>
            </div>

            <p x-show="feedback" x-text="feedback" x-cloak class="text-sm text-gray-600 dark:text-gray-300"></p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
