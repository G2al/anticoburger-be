<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(window.anticoBurgerPush.serviceWorkerUrl, { scope: '/' });
        });
    }
</script>
