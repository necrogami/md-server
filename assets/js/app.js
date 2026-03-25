(function() {
    'use strict';

    var THEME_KEY = 'md-server-theme';

    function getStoredTheme() {
        return localStorage.getItem(THEME_KEY);
    }

    function setTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
        var link = document.getElementById('theme-stylesheet');
        if (link) {
            link.href = '/_md/css/' + theme + '.css';
        }
        document.documentElement.setAttribute('data-theme', theme);
    }

    function getPreferredTheme() {
        var stored = getStoredTheme();
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    // Initialize theme
    var initialTheme = getPreferredTheme();
    setTheme(initialTheme);

    // Theme toggle (built-in only)
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', function() {
            var current = getStoredTheme() || getPreferredTheme();
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // Theme switcher dropdown (custom themes)
    var switcher = document.getElementById('theme-switcher');
    if (switcher) {
        switcher.value = initialTheme;
        switcher.addEventListener('change', function() {
            var selected = this.value;
            if (selected === 'light' || selected === 'dark') {
                setTheme(selected);
            } else {
                var link = document.getElementById('theme-stylesheet');
                if (link) {
                    link.href = '/_md/theme/' + selected + '.css';
                }
                localStorage.setItem(THEME_KEY, selected);
                document.documentElement.setAttribute('data-theme', selected);
            }
        });
    }

    // Listen for OS theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!getStoredTheme()) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Initialize Mermaid
    if (typeof mermaid !== 'undefined') {
        mermaid.initialize({
            startOnLoad: true,
            theme: initialTheme === 'dark' ? 'dark' : 'default',
        });
    }

    // Initialize KaTeX for block math
    document.querySelectorAll('.katex-block').forEach(function(el) {
        if (typeof katex !== 'undefined') {
            try {
                katex.render(el.textContent, el, {
                    displayMode: true,
                    throwOnError: false,
                });
            } catch (e) {
                console.warn('KaTeX render error:', e);
            }
        }
    });
})();
