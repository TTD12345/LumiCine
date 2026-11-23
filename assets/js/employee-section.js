(() => {
    'use strict';

    const sidebar = document.getElementById('employee-sidebar');
    const scrim = document.getElementById('sidebar-scrim');
    const accountButton = document.getElementById('account-menu-button');
    const accountMenu = document.getElementById('account-menu');

    document.getElementById('mobile-menu-button').addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-visible');
    });
    scrim.addEventListener('click', () => {
        sidebar.classList.remove('is-open');
        scrim.classList.remove('is-visible');
    });
    accountButton.addEventListener('click', () => {
        accountMenu.hidden = !accountMenu.hidden;
        accountButton.setAttribute('aria-expanded', String(!accountMenu.hidden));
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.topbar-account')) {
            accountMenu.hidden = true;
            accountButton.setAttribute('aria-expanded', 'false');
        }
    });
})();
