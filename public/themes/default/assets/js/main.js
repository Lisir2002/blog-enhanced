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
    var avatarToggle   = document.getElementById('avatarToggle');
    var userMenu       = document.getElementById('userMenu');
    var userMenuOverlay= document.getElementById('userMenuOverlay');
    var debugToggle    = document.getElementById('debugToggle');
    var debugBody      = document.getElementById('debugBody');

    // 判断当前是否为移动端布局（与 CSS 媒体查询断点一致）
    function isMobileLayout() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    // ─── 面板状态管理：body 滚动锁定 + 系统返回键处理 ──
    var overlayActive = false; // 是否有任何面板（弹窗/侧边栏）打开

    // 更新 body 滚动锁定状态
    function updateBodyScroll() {
        if (overlayActive) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // iOS 阻止 touchmove 冒泡到 body（解决 iOS 上 overflow:hidden 无效的问题）
    function preventTouchMove(e) {
        if (overlayActive) {
            // 允许 drawer 面板内部滚动
            var panel = navDrawer && navDrawer.classList.contains('is-open') ? navPanel : null;
            if (panel && panel.contains(e.target)) return;
            // 允许 popover 滚动区内部滚动
            var scrollArea = userMenu && userMenu.classList.contains('is-open')
                ? userMenu.querySelector('.blog-popover__scroll') : null;
            if (scrollArea && scrollArea.contains(e.target)) return;
            e.preventDefault();
        }
    }
    document.addEventListener('touchmove', preventTouchMove, { passive: false });

    // 系统返回键处理：关闭面板而非页面后退
    window.addEventListener('popstate', function () {
        if (overlayActive) {
            closeAllPanels();
        }
    });

    // 打开面板时同步历史状态
    function pushOverlayState() {
        if (!overlayActive) {
            overlayActive = true;
            history.pushState({ overlay: true }, '');
            updateBodyScroll();
        }
    }

    // 关闭所有面板
    function closeAllPanels() {
        overlayActive = false;
        if (navDrawer) {
            navDrawer.classList.remove('is-open');
            if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
        }
        if (userMenu) {
            userMenu.classList.remove('is-open');
            if (userMenuOverlay) userMenuOverlay.classList.remove('is-open');
            if (avatarToggle) avatarToggle.setAttribute('aria-expanded', 'false');
        }
        if (debugBody) debugBody.classList.remove('is-open');
        updateBodyScroll();
    }

    // ─── 导航抽屉 ──────────────────────────────────────
    function openNav() {
        if (navDrawer) navDrawer.classList.add('is-open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'true');
        if (searchPanel) searchPanel.classList.remove('is-open');
        if (userMenuOverlay) userMenuOverlay.classList.remove('is-open');
        if (userMenu) userMenu.classList.remove('is-open');
        if (avatarToggle) avatarToggle.setAttribute('aria-expanded', 'false');
        pushOverlayState();
    }
    function closeNav() {
        if (navDrawer) navDrawer.classList.remove('is-open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
        // 检查是否还有其他面板打开
        var otherOpen = userMenu && userMenu.classList.contains('is-open');
        if (!otherOpen) {
            overlayActive = false;
            updateBodyScroll();
        }
    }

    // ─── 用户菜单弹窗 ──────────────────────────────────
    function toggleUserMenu() {
        if (!userMenu) return;
        var isOpen = userMenu.classList.toggle('is-open');
        if (avatarToggle) avatarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (userMenuOverlay) userMenuOverlay.classList.toggle('is-open', isOpen);
        if (isOpen) {
            if (searchPanel) searchPanel.classList.remove('is-open');
            if (navDrawer) {
                navDrawer.classList.remove('is-open');
                if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
            }
            pushOverlayState();
        } else {
            // 检查是否还有其他面板打开
            var otherOpen = navDrawer && navDrawer.classList.contains('is-open');
            if (!otherOpen) {
                overlayActive = false;
                updateBodyScroll();
            }
        }
    }
    function closeUserMenu() {
        if (userMenu) userMenu.classList.remove('is-open');
        if (userMenuOverlay) userMenuOverlay.classList.remove('is-open');
        if (avatarToggle) avatarToggle.setAttribute('aria-expanded', 'false');
        if (debugBody) debugBody.classList.remove('is-open');
        var otherOpen = navDrawer && navDrawer.classList.contains('is-open');
        if (!otherOpen) {
            overlayActive = false;
            updateBodyScroll();
        }
    }

    if (navToggle) navToggle.addEventListener('click', openNav);
    if (navClose)  navClose.addEventListener('click', closeNav);
    if (navOverlay) navOverlay.addEventListener('click', closeNav);
    if (avatarToggle) avatarToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleUserMenu();
    });
    // 点击遮罩关闭（移动端）
    if (userMenuOverlay) userMenuOverlay.addEventListener('click', function () {
        closeUserMenu();
    });

    // 调试面板展开/收起
    if (debugToggle) debugToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (debugBody) debugBody.classList.toggle('is-open');
    });

    // ─── 实时搜索（纯客户端，零网络请求） ──────────────
    var searchResults = document.getElementById('searchResults');
    var searchIndexEl = document.getElementById('searchIndex');
    var searchIndex = [];
    try {
        searchIndex = JSON.parse(searchIndexEl ? searchIndexEl.textContent : '[]');
    } catch (e) {
        searchIndex = [];
    }
    var searchTimer = null;

    if (searchToggle && searchPanel) {
        searchToggle.addEventListener('click', function () {
            var expanded = searchPanel.classList.toggle('is-open');
            if (expanded) {
                if (searchInput) searchInput.focus();
                closeNav();
                closeUserMenu();
            } else {
                if (searchResults) searchResults.classList.remove('is-active');
            }
        });
    }
    if (searchClose) {
        searchClose.addEventListener('click', function () {
            searchPanel.classList.remove('is-open');
            if (searchResults) searchResults.classList.remove('is-active');
            if (searchToggle) searchToggle.focus();
        });
    }

    // 实时搜索：输入时客户端过滤
    if (searchInput && searchResults) {
        var isComposing = false;

        searchInput.addEventListener('compositionstart', function () {
            isComposing = true;
        });
        searchInput.addEventListener('compositionend', function () {
            isComposing = false;
            doSearch(this.value.trim());
        });
        searchInput.addEventListener('input', function () {
            if (isComposing) return;
            doSearch(this.value.trim());
        });

        // 点击搜索面板外部关闭结果
        document.addEventListener('click', function (e) {
            if (searchResults && searchResults.classList.contains('is-active')) {
                var panel = searchPanel;
                if (panel && !panel.contains(e.target) && e.target !== searchToggle) {
                    searchResults.classList.remove('is-active');
                }
            }
        });
    }

    function doSearch(q) {
        clearTimeout(searchTimer);
        if (q === '') {
            searchResults.classList.remove('is-active');
            return;
        }
        // 防抖：避免快速输入时频繁过滤
        searchTimer = setTimeout(function () {
            var lower = q.toLowerCase();
            var matched = searchIndex.filter(function (post) {
                return (post.title && post.title.toLowerCase().indexOf(lower) !== -1)
                    || (post.excerpt && post.excerpt.toLowerCase().indexOf(lower) !== -1);
            }).slice(0, 5);

            if (matched.length === 0) {
                searchResults.innerHTML = '<div class="blog-search__results-empty">没有找到相关文章</div>';
            } else {
                var html = '';
                matched.forEach(function (post) {
                    html += '<a href="' + post.url + '" class="blog-search__results-item">'
                        + '<span class="blog-search__results-title">' + escapeHtml(post.title) + '</span>'
                        + '<span class="blog-search__results-excerpt">' + escapeHtml(post.excerpt) + '</span>'
                        + '</a>';
                });
                html += '<div class="blog-search__results-footer">'
                    + '<a href="/search?q=' + encodeURIComponent(q) + '">查看全部搜索结果 →</a>'
                    + '</div>';
                searchResults.innerHTML = html;
            }
            searchResults.classList.add('is-active');
        }, 150);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // 桌面端：点击外部关闭气泡菜单
    document.addEventListener('click', function (e) {
        if (userMenu && userMenu.classList.contains('is-open') && !isMobileLayout()) {
            var wrap = document.querySelector('.blog-header__avatar-wrap');
            if ((wrap && wrap.contains(e.target)) || userMenu.contains(e.target)) return;
            closeUserMenu();
        }
    });

    // 桌面端 → 移动端切换时，同步恢复 body 滚动状态
    window.matchMedia('(max-width: 768px)').addEventListener('change', function (e) {
        if (!e.matches) {
            // 切到桌面端：若菜单仍打开，恢复 body 滚动
            if (userMenu && userMenu.classList.contains('is-open')) {
                document.body.style.overflow = '';
            }
        } else {
            // 切到移动端：若导航打开，保持滚动锁定
            if (navDrawer && navDrawer.classList.contains('is-open')) {
                document.body.style.overflow = 'hidden';
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
        themeToggle.addEventListener('click', function (e) {
            e.stopPropagation();
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
