/* =============================================================
   THE GREEN BROOM — nav.js (Version 1.2)
   ============================================================= */

   document.addEventListener('DOMContentLoaded', function() {
    console.log('The Green Broom: Nav JS Loaded'); // Debug Message 1

    // 1. MOBILE MENU TOGGLE
    const menuToggle = document.querySelector('.menu-toggle');
    const siteHeader = document.querySelector('.site-header');

    if (menuToggle) {
        console.log('The Green Broom: Hamburger button found'); // Debug Message 2

        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('The Green Broom: Hamburger clicked'); // Debug Message 3
            
            // Toggle the open class
            siteHeader.classList.toggle('tgb-nav-open');
            
            // Handle aria attributes
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !isExpanded);
        });
    } else {
        console.error('The Green Broom: Hamburger button NOT found. Check class .menu-toggle');
    }

    // 2. MOBILE ACCORDION (Submenus)
    const parentLinks = document.querySelectorAll('.menu-item-has-children > a');
    
    parentLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Only on Mobile (screen width < 992px)
            if (window.innerWidth < 992) {
                e.preventDefault();
                console.log('The Green Broom: Submenu clicked');
                
                // Toggle this specific submenu
                this.parentElement.classList.toggle('tgb-submenu-open');
            }
        });
    });

});