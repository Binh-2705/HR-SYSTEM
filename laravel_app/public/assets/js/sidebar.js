document.addEventListener("DOMContentLoaded", function() {
    var body = document.body;
    var sidebar = document.getElementById('appSidebar');
    var mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    var sidebarOverlay = document.getElementById('sidebarOverlay');


    function hideProblemOverlays(root) {
        var scope = root || document;
        var selectors = [
            'veepn-lock-screen',
            'veepn-guard',
            '[id*="veepn" i]',
            '[class*="veepn" i]'
        ];

        selectors.forEach(function(selector) {
            scope.querySelectorAll(selector).forEach(function(el) {
                el.style.setProperty('display', 'none', 'important');
                el.style.setProperty('visibility', 'hidden', 'important');
                el.style.setProperty('pointer-events', 'none', 'important');
                el.style.setProperty('opacity', '0', 'important');
            });
        });

        scope.querySelectorAll('*').forEach(function(el) {
            if (!el || !el.textContent) {
                return;
            }

            var text = (el.textContent || '').trim();
            if (text !== '‹' && text !== '❮' && text !== '←') {
                return;
            }

            var style = window.getComputedStyle(el);
            var isFixed = style.position === 'fixed' || style.position === 'sticky';
            var fontSize = parseFloat(style.fontSize || '0');

            if (isFixed && fontSize >= 48) {
                el.style.setProperty('display', 'none', 'important');
                el.style.setProperty('visibility', 'hidden', 'important');
                el.style.setProperty('pointer-events', 'none', 'important');
                el.style.setProperty('opacity', '0', 'important');
            }
        });
    }

    function setSidebarOpen(isOpen) {
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('open', isOpen);

        if (body) {
            body.classList.toggle('sidebar-open', isOpen);
        }

        if (mobileSidebarToggle) {
            mobileSidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (sidebarOverlay) {
            sidebarOverlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }
    }

    document.querySelectorAll('.menu-toggle').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.classList.toggle('open');
        });
    });

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            var shouldOpen = !sidebar || !sidebar.classList.contains('open');
            setSidebarOpen(shouldOpen);
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            setSidebarOpen(false);
        });
    }

    var accountWidget = document.getElementById('sidebarAccountWidget');
    var accountTrigger = document.getElementById('sidebarAccountTrigger');

    if (accountWidget && accountTrigger) {
        accountWidget.classList.remove('open');
        accountTrigger.setAttribute('aria-expanded', 'false');

        accountTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = accountWidget.classList.toggle('open');
            accountTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function(e) {
            if (!accountWidget.contains(e.target)) {
                accountWidget.classList.remove('open');
                accountTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            setSidebarOpen(false);
        }
    });



    hideProblemOverlays(document);

    var overlayObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node && node.nodeType === 1) {
                    hideProblemOverlays(node);
                }
            });
        });
    });

    overlayObserver.observe(document.documentElement || document.body, {
        childList: true,
        subtree: true
    });
    
});