// Default theme - minimal JS, progressive enhancement
(function () {
    'use strict';

    // ─── 导航抽屉展开/收起 ──────────────────────────────
    var navToggle  = document.getElementById('navToggle');
    var navDrawer  = document.getElementById('navDrawer');
    var navPanel   = document.getElementById('navPanel');
    var navOverlay = document.getElementById('navOverlay');
    var navClose   = document.getElementById('navClose');

    // ─── 搜索面板 ──────────────────────────────────────
    var searchToggle = document.getElementById('searchToggle');
    var searchPanel  = document.getElementById('searchPanel');
    var searchClose  = document.getElementById('searchClose');
    var searchInput  = document.querySelector('.blog-search__input');

    // ─── 头像气泡菜单 ──────────────────────────────────
    var avatarToggle = document.getElementById('avatarToggle');
    var userMenu     = document.getElementById('userMenu');
    var debugToggle  = document.getElementById('debugToggle');
    var debugBody    = document.getElementById('debugBody');

    function openNav() {
        if (navDrawer) navDrawer.classList.add('is-open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        if (searchPanel) searchPanel.classList.remove('is-open');
        closeUserMenu();
    }
    function closeNav() {
        if (navDrawer) navDrawer.classList.remove('is-open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function toggleUserMenu() {
        if (!userMenu) return;
        var isOpen = userMenu.classList.toggle('is-open');
        if (avatarToggle) avatarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            if (searchPanel) searchPanel.classList.remove('is-open');
            closeNav();
        }
    }
    function closeUserMenu() {
        if (userMenu) userMenu.classList.remove('is-open');
        if (avatarToggle) avatarToggle.setAttribute('aria-expanded', 'false');
        if (debugBody) debugBody.classList.remove('is-open');
    }

    if (navToggle) navToggle.addEventListener('click', openNav);
    if (navClose)  navClose.addEventListener('click', closeNav);
    if (navOverlay) navOverlay.addEventListener('click', closeNav);
    if (avatarToggle) avatarToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleUserMenu();
    });

    // 调试面板展开/收起
    if (debugToggle) debugToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (debugBody) debugBody.classList.toggle('is-open');
    });

    // 搜索
    if (searchToggle && searchPanel) {
        searchToggle.addEventListener('click', function () {
            var expanded = searchPanel.classList.toggle('is-open');
            if (expanded) {
                if (searchInput) searchInput.focus();
                closeNav();
                closeUserMenu();
            }
        });
    }
    if (searchClose) {
        searchClose.addEventListener('click', function () {
            searchPanel.classList.remove('is-open');
            if (searchToggle) searchToggle.focus();
        });
    }

    // 点击外部关闭气泡菜单
    document.addEventListener('click', function (e) {
        if (userMenu && userMenu.classList.contains('is-open')) {
            var wrap = document.querySelector('.blog-header__avatar-wrap');
            if (wrap && !wrap.contains(e.target)) {
                closeUserMenu();
            }
        }
    });

    // Esc 关闭所有面板
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (searchPanel && searchPanel.classList.contains('is-open')) {
            searchPanel.classList.remove('is-open');
            if (searchToggle) searchToggle.focus();
        }
        if (navDrawer && navDrawer.classList.contains('is-open')) {
            closeNav();
            if (navToggle) navToggle.focus();
        }
        if (userMenu && userMenu.classList.contains('is-open')) {
            closeUserMenu();
            if (avatarToggle) avatarToggle.focus();
        }
    });

    // ─── 主题切换（深色/浅色） ──────────────────────────
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var html = document.documentElement;
            var current = html.getAttribute('data-theme');
            var next;
            if (current === 'dark') {
                next = 'light';
            } else if (current === 'light') {
                next = '';
            } else {
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                next = prefersDark ? 'light' : 'dark';
            }
            if (next) {
                html.setAttribute('data-theme', next);
            } else {
                html.removeAttribute('data-theme');
            }
            document.cookie = 'theme=' + next + ';path=/;max-age=31536000;samesite=lax';
        });
    }

    // ─── 点击导航链接后自动关闭抽屉 ─────────────────────
    if (navPanel) {
        navPanel.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeNav);
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

    // Highlight current menu item
    var path = window.location.pathname;
    document.querySelectorAll('.blog-nav__item a').forEach(function (a) {
        var href = a.getAttribute('href');
        if (!href) return;
        if (path === href || (href !== '/' && path.indexOf(href) === 0)) {
            a.parentElement.classList.add('blog-nav__item--active');
        }
    });

    // ─── Floating TOC ──────────────────────────────────
    (function () {
        var tocFloat = document.getElementById('floatingToc');
        if (!tocFloat) return;

        var toggle = tocFloat.querySelector('.blog-toc-float__toggle');
        var links = tocFloat.querySelectorAll('.blog-toc__link');
        if (!toggle) return;

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            tocFloat.classList.toggle('is-open');
        });

        document.addEventListener('click', function (e) {
            if (tocFloat.classList.contains('is-open') && !tocFloat.contains(e.target)) {
                tocFloat.classList.remove('is-open');
            }
        });

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
})();
