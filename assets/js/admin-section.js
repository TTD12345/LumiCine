(() => {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const accountButton = document.getElementById('admin-account-menu-button');
    const accountMenu = document.getElementById('admin-account-menu');

    document.getElementById('sidebar-toggle').addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
        backdrop.classList.toggle('is-visible');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-visible');
    });
    accountButton.addEventListener('click', () => {
        accountMenu.hidden = !accountMenu.hidden;
        accountButton.setAttribute('aria-expanded', String(!accountMenu.hidden));
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.topbar-actions')) {
            accountMenu.hidden = true;
            accountButton.setAttribute('aria-expanded', 'false');
        }
    });
    document.getElementById('notification-button').addEventListener('click', () => {
        const toast = document.createElement('div');
        toast.className = 'employee-toast';
        toast.textContent = 'Không có thông báo mới.';
        document.getElementById('toast-container').append(toast);
        window.setTimeout(() => toast.remove(), 4000);
    });
})();
