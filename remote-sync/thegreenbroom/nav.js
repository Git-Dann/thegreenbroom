document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.menu-toggle');
    var navigation = document.querySelector('.main-navigation');

    if (!toggle || !navigation) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = navigation.classList.toggle('toggled');
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    });

    document.querySelectorAll('.menu-parent-toggle').forEach(function (menuToggle) {
        menuToggle.addEventListener('click', function () {
            var isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('.menu-parent-toggle').forEach(function (otherToggle) {
                otherToggle.setAttribute('aria-expanded', 'false');
            });
            menuToggle.setAttribute('aria-expanded', String(!isOpen));
        });
    });

    /*
     * A dropdown opened by a click used to stay pinned open: moving the mouse
     * away or clicking anywhere else left it hanging over the page, and only
     * a second click on its own button (or Escape) shut it. It now also
     * closes when you click elsewhere, when the mouse leaves it, and when
     * keyboard focus moves out of it.
     */
    function closeMenu(item) {
        var menuToggle = item.querySelector('.menu-parent-toggle');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.menu-item-has-children').forEach(function (item) {
            if (!item.contains(event.target)) {
                closeMenu(item);
            }
        });
    });

    document.querySelectorAll('.menu-item-has-children').forEach(function (item) {
        item.addEventListener('mouseleave', function (event) {
            // Touch screens fire mouseleave on the next tap anywhere; the
            // outside-click handler above already covers that.
            if (event.pointerType === 'touch' || !window.matchMedia('(hover: hover)').matches) {
                return;
            }
            closeMenu(item);
        });

        item.addEventListener('focusout', function (event) {
            if (event.relatedTarget && !item.contains(event.relatedTarget)) {
                closeMenu(item);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.menu-parent-toggle[aria-expanded="true"]').forEach(function (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.focus();
        });
    });
});
