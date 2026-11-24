{{-- Inline poller + AJAX reply for the modern support desk. --}}
<script>
(() => {
    const root = document.querySelector('[data-support-thread]');
    const list = root?.querySelector('[data-support-messages]');
    const pollUrl = root?.getAttribute('data-support-poll');
    const threads = document.querySelector('[data-support-rows]');
    const threadsUrl = threads?.getAttribute('data-support-poll-index');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const headers = () => ({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
    });

    const salesLabel = root?.getAttribute('data-label-sales') || 'Sales';
    const userLabel = root?.getAttribute('data-label-user') || ('USR-' + (root?.getAttribute('data-user-id') || ''));

    const latestId = () => {
        if (! list) {
            return 0;
        }
        let max = 0;
        list.querySelectorAll('[data-message-id]').forEach((node) => {
            max = Math.max(max, Number(node.getAttribute('data-message-id') || 0));
        });
        return max;
    };

    const appendMessage = (message) => {
        if (! list || ! message?.id || list.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        const fromSales = message.author === 'sales';
        const row = document.createElement('div');
        row.className = `support-bubble ${fromSales ? 'is-sales' : 'is-user'}`;
        row.setAttribute('data-message-id', String(message.id));
        row.setAttribute('data-author', fromSales ? 'sales' : 'user');

        const meta = document.createElement('div');
        meta.className = 'support-bubble-meta';
        const name = document.createElement('b');
        name.textContent = fromSales ? salesLabel : userLabel;
        const time = document.createElement('span');
        time.className = 'tiny en';
        time.textContent = message.created_at
            ? new Date(message.created_at).toISOString().slice(0, 16).replace('T', ' ')
            : '';
        meta.append(name, time);

        const body = document.createElement('div');
        body.className = 'support-bubble-body';
        body.textContent = message.body || '';

        row.append(meta, body);
        list.append(row);
        list.scrollTop = list.scrollHeight;
    };

    const upsertThread = (thread) => {
        if (! threads || ! thread?.id) {
            return;
        }

        threads.querySelector('[data-support-empty]')?.remove();

        let item = threads.querySelector(`[data-thread-id="${thread.id}"]`);
        if (! item) {
            item = document.createElement('a');
            item.className = 'support-thread-item';
            item.setAttribute('data-thread-id', String(thread.id));
            item.href = thread.url || (`/dashboard/support/${thread.id}`);
            item.innerHTML = `
                <div class="support-thread-avatar en"></div>
                <div class="support-thread-meta">
                    <div class="support-thread-top"><b></b><span class="tiny en"></span></div>
                    <div class="support-thread-sub"><span class="en"></span><span class="badge grey"></span></div>
                    <p class="support-thread-preview"></p>
                </div>
            `;
            threads.prepend(item);
        }

        const name = thread.user_name || `USR-${thread.user_id}`;
        item.querySelector('.support-thread-avatar').textContent = String(name).slice(0, 1).toUpperCase();
        item.querySelector('.support-thread-top b').textContent = name;
        item.querySelector('.support-thread-top .tiny').textContent = thread.preview_at
            ? new Date(thread.preview_at).toISOString().slice(11, 16)
            : '';
        item.querySelector('.support-thread-sub .en').textContent = thread.public_code || '';
        const badge = item.querySelector('.badge');
        badge.textContent = thread.status || '';
        badge.className = `badge ${thread.status === 'open' ? 'ok' : 'grey'}`;
        item.querySelector('.support-thread-preview').textContent = thread.preview || '';
        if (thread.url) {
            item.setAttribute('href', thread.url);
        }
    };

    const pollThread = async () => {
        if (! root || ! list || ! pollUrl || document.hidden) {
            return;
        }

        try {
            const response = await fetch(`${pollUrl}?after_id=${latestId()}`, {
                headers: headers(),
                credentials: 'same-origin',
            });
            if (! response.ok) {
                return;
            }
            const payload = await response.json();
            const data = payload?.data || {};
            (data.messages || []).forEach(appendMessage);
            if (data.status === 'closed') {
                root.querySelector('.support-composer')?.setAttribute('hidden', 'hidden');
            }
        } catch (error) {
            console.warn('aqdar.support_inline_poll_failed', error);
        }
    };

    const pollThreads = async () => {
        if (! threads || ! threadsUrl || document.hidden) {
            return;
        }

        try {
            const response = await fetch(threadsUrl, {
                headers: headers(),
                credentials: 'same-origin',
            });
            if (! response.ok) {
                return;
            }
            const payload = await response.json();
            (payload?.data || []).forEach(upsertThread);
        } catch (error) {
            console.warn('aqdar.support_index_poll_failed', error);
        }
    };

    const form = root?.querySelector('.support-composer');
    const textarea = form?.querySelector('textarea[name="body"]');
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
                        ...headers(),
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({ _token: csrf, body }),
                });

                if (response.ok) {
                    const payload = await response.json();
                    if (payload?.data?.message) {
                        appendMessage(payload.data.message);
                    }
                    textarea.value = '';
                    await pollThread();
                    await pollThreads();
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

    if (list) {
        list.scrollTop = list.scrollHeight;
    }

    pollThread();
    pollThreads();
    window.setInterval(() => {
        pollThread();
        pollThreads();
    }, 2000);
})();
</script>
