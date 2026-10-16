<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@js(asset('sw.js')), { scope: '/' });
        });
    }
</script>
