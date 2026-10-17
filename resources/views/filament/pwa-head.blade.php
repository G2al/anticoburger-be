<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#18181b" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Antico Burger">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="application-name" content="Antico Burger">
<style>
    .fi-simple-layout .fi-logo {
        height: 4.5rem !important;
    }
</style>
<script>
    window.anticoBurgerPush = {
        publicKey: @js(config('push.vapid.public_key')),
        serviceWorkerUrl: @js(asset('sw.js')),
        urls: {
            store: @js(route('admin.push-subscriptions.store')),
            destroy: @js(route('admin.push-subscriptions.destroy')),
            test: @js(route('admin.push-subscriptions.test')),
        },

        isSupported() {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        },

        isIos() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        },

        isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        },

        async registration() {
            await navigator.serviceWorker.register(this.serviceWorkerUrl, { scope: '/' });

            return navigator.serviceWorker.ready;
        },

        // Returns one of: not-configured, ios-install, unsupported, denied, on, off.
        async status() {
            if (! this.publicKey) {
                return 'not-configured';
            }

            if (! this.isSupported()) {
                return this.isIos() && ! this.isStandalone() ? 'ios-install' : 'unsupported';
            }

            if (Notification.permission === 'denied') {
                return 'denied';
            }

            const subscription = await (await this.registration()).pushManager.getSubscription();

            if (subscription && Notification.permission === 'granted') {
                await this.save(subscription);

                return 'on';
            }

            return 'off';
        },

        async enable() {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                return permission === 'denied' ? 'denied' : 'off';
            }

            const registration = await this.registration();
            let subscription;

            try {
                subscription = await registration.pushManager.getSubscription()
                    ?? await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.decodeKey(this.publicKey),
                    });
            } catch (error) {
                throw new Error('Il browser non ha consentito l\'attivazione delle notifiche (ad esempio in navigazione privata). Riprova dall\'app installata o da una finestra normale.');
            }

            await this.save(subscription);

            return 'on';
        },

        async disable() {
            const subscription = await (await this.registration()).pushManager.getSubscription();

            if (subscription) {
                await this.request('DELETE', this.urls.destroy, { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            return 'off';
        },

        async sendTest() {
            return (await this.request('POST', this.urls.test)).data.sent;
        },

        async save(subscription) {
            const json = subscription.toJSON();
            const encodings = window.PushManager?.supportedContentEncodings ?? ['aes128gcm'];

            await this.request('POST', this.urls.store, {
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0],
            });
        },

        async request(method, url, body = null) {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: body ? JSON.stringify(body) : null,
            });

            const data = response.status === 204 ? null : await response.json().catch(() => null);

            if (! response.ok) {
                throw new Error(data?.message ?? 'Richiesta non riuscita.');
            }

            return data;
        },

        decodeKey(base64Url) {
            const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
            const raw = atob((base64Url + padding).replace(/-/g, '+').replace(/_/g, '/'));

            return Uint8Array.from(raw, (char) => char.charCodeAt(0));
        },
    };
</script>
