{{-- Inline poller + AJAX reply: works even when Vite/Echo/Pusher assets fail to load. --}}
<script>
(() => {
    const root = document.querySelector('[data-support-thread]');
    const list = root?.querySelector('[data-support-messages]');
    const pollUrl = root?.getAttribute('data-support-poll');
    if (! root || ! list || ! pollUrl) {
        return;
    }

    const salesLabel = root.getAttribute('data-label-sales') || 'Sales';
    const userId = root.getAttribute('data-user-id') || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const form = root.querySelector('form[action*="/reply"]') || root.querySelector('form');
    const textarea = form?.querySelector('textarea[name="body"]');

    const latestId = () => {
        let max = 0;
        list.querySelectorAll('[data-message-id]').forEach((node) => {
            max = Math.max(max, Number(node.getAttribute('data-message-id') || 0));
        });
        return max;
    };

    const appendMessage = (message) => {
        if (! message?.id || list.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'dl';
        row.setAttribute('data-message-id', String(message.id));

        const author = document.createElement('span');
        author.textContent = message.author === 'sales' ? salesLabel : `USR-${userId}`;

        const body = document.createElement('span');
        body.textContent = message.body || '';

        row.append(author, body);
        list.append(row);
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const poll = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(`${pollUrl}?after_id=${latestId()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            const data = payload?.data || {};
            (data.messages || []).forEach(appendMessage);

            if (data.status === 'closed') {
                form?.setAttribute('hidden', 'hidden');
            }
        } catch (error) {
            console.warn('aqdar.support_inline_poll_failed', error);
        }
    };

    if (form && textarea) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const body = textarea.value.trim();
            if (! body) {
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }

            try {
                const response = await fetch(form.getAttribute('action'), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        _token: csrf,
                        body,
                    }),
                });

                if (response.ok) {
                    const payload = await response.json();
                    if (payload?.data?.message) {
                        appendMessage(payload.data.message);
                    }
                    textarea.value = '';
                    await poll();
                }
            } catch (error) {
                console.warn('aqdar.support_reply_failed', error);
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        });
    }

    poll();
    window.setInterval(poll, 2000);
})();
</script>
