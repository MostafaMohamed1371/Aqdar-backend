import { getEcho } from './echo';

const bootSupportEcho = () => {
    const root = document.querySelector('[data-support-thread]');
    if (! root) {
        return;
    }

    const threadId = root.getAttribute('data-support-thread');
    const list = root.querySelector('[data-support-messages]');
    const echo = getEcho();
    if (! threadId || ! list || ! echo) {
        return;
    }

    echo.private(`support.thread.${threadId}`)
        .listen('.support.message.sent', (payload) => {
            const message = payload?.message;
            if (! message?.id || list.querySelector(`[data-message-id="${message.id}"]`)) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'dl';
            row.setAttribute('data-message-id', String(message.id));

            const author = document.createElement('span');
            author.textContent = message.author === 'sales'
                ? (root.getAttribute('data-label-sales') || 'Sales')
                : `USR-${root.getAttribute('data-user-id') || ''}`;

            const body = document.createElement('span');
            body.textContent = message.body || '';

            row.append(author, body);
            list.append(row);
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .listen('.support.thread.closed', () => {
            window.location.reload();
        });
};

const ready = (fn) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
};

ready(bootSupportEcho);
