document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.menu-toggle');
    var navigation = document.querySelector('.main-navigation');

    if (!toggle || !navigation) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = navigation.classList.toggle('toggled');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
});
