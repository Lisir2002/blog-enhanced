// Default theme - minimal JS, progressive enhancement
(function () {
    'use strict';

    // Mobile menu toggle (BEM: blog-header__toggle + blog-nav)
    var toggle = document.querySelector('.blog-header__toggle');
    var nav = document.querySelector('.blog-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var expanded = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    // Lazy-load images below the fold
    if ('loading' in HTMLImageElement.prototype) {
        // native lazy loading supported
    } else if (window.IntersectionObserver) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    io.unobserve(img);
                }
            });
        });
        document.querySelectorAll('img[data-src]').forEach(function (img) {
            io.observe(img);
        });
    }

    // Smooth scroll for anchor links inside content
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var id = a.getAttribute('href').slice(1);
            if (!id) return;
            var el = document.getElementById(id);
            if (el) {
                e.preventDefault();
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Highlight current menu item more precisely
    var path = window.location.pathname;
    document.querySelectorAll('.blog-nav__item a').forEach(function (a) {
        var href = a.getAttribute('href');
        if (!href) return;
        if (path === href || (href !== '/' && path.indexOf(href) === 0)) {
            a.parentElement.classList.add('blog-nav__item--active');
        }
    });

    // ─── Floating TOC ──────────────────────────────────────
    (function () {
        var tocFloat = document.getElementById('floatingToc');
        if (!tocFloat) return;

        var toggle = tocFloat.querySelector('.blog-toc-float__toggle');
        var links = tocFloat.querySelectorAll('.blog-toc__link');
        if (!toggle) return;

        // Toggle expand/collapse
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            tocFloat.classList.toggle('is-open');
        });

        // Close panel when clicking outside
        document.addEventListener('click', function (e) {
            if (tocFloat.classList.contains('is-open') && !tocFloat.contains(e.target)) {
                tocFloat.classList.remove('is-open');
            }
        });

        // Scroll tracking with Intersection Observer
        var headings = [];
        links.forEach(function (link) {
            var href = link.getAttribute('href');
            if (href && href.charAt(0) === '#') {
                var target = document.getElementById(href.slice(1));
                if (target) {
                    headings.push({ el: target, link: link });
                }
            }
        });

        if (headings.length > 0 && window.IntersectionObserver) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var id = entry.target.id;
                        links.forEach(function (l) {
                            l.classList.toggle('is-active', l.getAttribute('href') === '#' + id);
                        });
                    }
                });
            }, { rootMargin: '-80px 0px -80% 0px' });

            headings.forEach(function (h) { observer.observe(h.el); });
        }

        // Smooth scroll on link click
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var href = link.getAttribute('href');
                if (href && href.charAt(0) === '#') {
                    e.preventDefault();
                    var target = document.getElementById(href.slice(1));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    })();

    // ─── Mobile TOC toggle ─────────────────────────────────
    (function () {
        var tocMobile = document.getElementById('tocMobile');
        if (!tocMobile) return;
        var toggle = tocMobile.querySelector('.blog-toc-mobile__toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                tocMobile.classList.toggle('is-open');
            });
        }
    })();
})();
