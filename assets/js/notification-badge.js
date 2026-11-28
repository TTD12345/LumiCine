(() => {
    'use strict';

    const button = document.querySelector('.notification-button');
    const indicator = document.getElementById('notification-indicator');
    if (!button || !indicator || typeof window.lumiCineApi !== 'function') return;
    let previousCount = null;

    const updateCount = async () => {
        try {
            const result = await window.lumiCineApi('notification-count');
            const count = Number(result.count);
            if (!Number.isInteger(count) || count < 0) {
                throw new Error('Số lượng thông báo không hợp lệ.');
            }
            indicator.textContent = count > 99 ? '99+' : String(count);
            indicator.hidden = count === 0;
            button.setAttribute('aria-label', count > 0 ? `Thông báo, ${count} mục` : 'Thông báo');
            if (previousCount !== null && count > previousCount) {
                const region = document.querySelector('.toast-region, #toast-container');
                if (region) {
                    const toast = document.createElement('div');
                    toast.className = 'notification-arrival-toast';
                    const newCount = count - previousCount;
                    toast.textContent = newCount === 1
                        ? 'Bạn có thông báo mới.'
                        : `Bạn có ${newCount} thông báo mới.`;
                    region.append(toast);
                    window.setTimeout(() => toast.remove(), 5000);
                }
            }
            previousCount = count;
        } catch (error) {
            console.error('Không thể cập nhật số lượng thông báo:', error);
        }
    };

    button.addEventListener('click', () => {
        window.location.href = button.dataset.notificationUrl;
    });
    updateCount();
    window.setInterval(updateCount, 30000);
    window.addEventListener('focus', updateCount);
    window.addEventListener('lumicine:notifications-read', updateCount);
})();
