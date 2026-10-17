@can('receive-push-notifications')
    <div
        x-data="{
            state: 'loading',
            busy: false,
            async init() {
                try {
                    this.state = await window.anticoBurgerPush.status()
                } catch (error) {
                    this.state = 'off'
                }
            },
            notify(title, body, status) {
                new FilamentNotification().title(title).body(body ?? null).status(status).send()
            },
            async toggle() {
                if (this.busy) {
                    return
                }

                if (this.state === 'denied') {
                    return this.notify('Notifiche bloccate', 'Abilitale per questo sito dalle impostazioni del browser o del telefono, poi ricarica la pagina.', 'warning')
                }

                if (this.state === 'ios-install') {
                    return this.notify('Installa il gestionale', 'Su iPhone le notifiche funzionano solo dall\'app: in Safari tocca Condividi → «Aggiungi alla schermata Home», poi aprila dall\'icona (iOS 16.4 o successivo).', 'warning')
                }

                if (this.state === 'unsupported') {
                    return this.notify('Notifiche non supportate', 'Questo browser non supporta le notifiche push.', 'warning')
                }

                this.busy = true

                try {
                    if (this.state === 'on') {
                        this.state = await window.anticoBurgerPush.disable()
                        this.notify('Notifiche disattivate', 'Questo dispositivo non riceverà più le nuove prenotazioni.', 'success')
                    } else {
                        this.state = await window.anticoBurgerPush.enable()

                        if (this.state === 'on') {
                            await window.anticoBurgerPush.sendTest()
                            this.notify('Notifiche attive', 'Riceverai una notifica per ogni nuova prenotazione online. Ti abbiamo inviato una notifica di prova.', 'success')
                        } else if (this.state === 'denied') {
                            this.notify('Notifiche bloccate', 'Hai negato il permesso: abilitalo dalle impostazioni del browser o del telefono.', 'warning')
                        }
                    }
                } catch (error) {
                    this.notify('Operazione non riuscita', error.message, 'danger')
                } finally {
                    this.busy = false
                }
            },
        }"
        x-show="state !== 'loading' && state !== 'not-configured'"
        x-cloak
        class="flex items-center"
    >
        <x-filament::icon-button
            x-show="state === 'on'"
            x-on:click="toggle()"
            x-bind:disabled="busy"
            icon="heroicon-s-bell-alert"
            color="primary"
            size="lg"
            label="Notifiche attive: tocca per disattivarle"
            tooltip="Notifiche attive"
        />

        <x-filament::icon-button
            x-show="state !== 'on'"
            x-on:click="toggle()"
            x-bind:disabled="busy"
            icon="heroicon-o-bell-slash"
            color="gray"
            size="lg"
            label="Notifiche disattivate: tocca per attivarle"
            tooltip="Attiva le notifiche"
        />
    </div>
@endcan
