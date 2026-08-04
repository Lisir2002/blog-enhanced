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
})();
