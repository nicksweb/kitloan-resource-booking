@props(['theme' => 'system'])
{{-- Resolves the colour theme and toggles `.dark` on <html> before first
     paint (no flash). For a signed-in user the server value is authoritative
     and is mirrored into localStorage so the sign-in screen matches next
     time; for a guest, localStorage (or the OS setting) decides. --}}
<script>
    (function () {
        var serverTheme = @js($theme);
        var theme = serverTheme;
        try {
            @auth
                localStorage.setItem('theme', serverTheme);
            @else
                theme = localStorage.getItem('theme') || 'system';
            @endauth
        } catch (e) {}

        function apply(t) {
            var dark = t === 'dark' ||
                (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }

        apply(theme);

        try {
            // Livewire (My Profile) fires this on change/save.
            window.addEventListener('theme-changed', function (e) {
                var t = (e.detail && e.detail.theme) || 'system';
                try { localStorage.setItem('theme', t); } catch (x) {}
                apply(t);
            });

            // Follow the OS while "system" is in effect and the page stays open.
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                var current = 'system';
                try { current = localStorage.getItem('theme') || 'system'; } catch (x) {}
                if (current === 'system') {
                    document.documentElement.classList.toggle('dark', e.matches);
                }
            });
        } catch (e) {}
    })();
</script>
