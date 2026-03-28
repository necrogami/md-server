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

    // Build table of contents from headings
    (function() {
        var tocList = document.querySelector('.md-toc-list');
        var tocNav = document.querySelector('.md-toc');
        if (!tocList || !tocNav) return;

        var article = document.querySelector('.md-article');
        if (!article) return;

        var headings = article.querySelectorAll('h2, h3');
        if (headings.length < 2) {
            tocNav.style.display = 'none';
            return;
        }

        headings.forEach(function(heading, i) {
            if (!heading.id) {
                heading.id = 'heading-' + i + '-' + heading.textContent
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .substring(0, 60);
            }

            var li = document.createElement('li');
            li.className = 'md-toc-item';
            if (heading.tagName === 'H3') {
                li.className += ' md-toc-item-h3';
            }

            var a = document.createElement('a');
            a.href = '#' + heading.id;
            a.textContent = heading.textContent;
            li.appendChild(a);
            tocList.appendChild(li);
        });

        // Scroll spy
        var tocLinks = tocList.querySelectorAll('a');
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    tocLinks.forEach(function(link) {
                        link.classList.remove('active');
                    });
                    var active = tocList.querySelector('a[href="#' + entry.target.id + '"]');
                    if (active) active.classList.add('active');
                }
            });
        }, { rootMargin: '0px 0px -70% 0px', threshold: 0 });

        headings.forEach(function(heading) {
            observer.observe(heading);
        });
    })();
})();
