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

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.menu-parent-toggle[aria-expanded="true"]').forEach(function (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.focus();
        });
    });
});
