import { getEcho } from './echo';

const isTyping = () => {
    const el = document.activeElement;

    return Boolean(el && el.matches('input, textarea, select, [contenteditable="true"]'));
};

const bootDashboardRealtime = () => {
    const desk = document.querySelector('[data-staff-id]');
    if (! desk) {
        return;
    }

    const staffId = desk.getAttribute('data-staff-id');
    const echo = getEcho();
    if (! staffId || ! echo) {
        return;
    }

    const main = document.querySelector('[data-screen]');
    const screen = main?.getAttribute('data-screen') || '';
    const bell = document.querySelector('[data-inbox-bell]');
    const dot = document.querySelector('[data-inbox-dot]');
    const inboxBody = document.querySelector('[data-inbox-rows]');
    const supportBody = document.querySelector('[data-support-rows]');
    const unreadLabel = desk.getAttribute('data-label-unread') || 'Unread';
    const viewLabel = desk.getAttribute('data-label-view') || 'View';

    let reloadTimer = null;

    const showBell = (count) => {
        if (! bell || ! dot) {
            return;
        }
        if (count > 0) {
            dot.hidden = false;
            bell.setAttribute('data-unread', String(count));
        } else {
            dot.hidden = true;
            bell.setAttribute('data-unread', '0');
        }
    };

    const softReload = () => {
        if (isTyping()) {
            return;
        }
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            if (isTyping()) {
                return;
            }
            window.location.reload();
        }, 500);
    };

    const screenMatches = (payload) => {
        const screens = Array.isArray(payload?.screens) ? payload.screens : [];
        if (screens.length === 0) {
            return true;
        }

        return screens.includes(screen);
    };

    // Every staff page: soft-reload when matching desk data changes.
    echo.private('desk')
        .listen('.desk.updated', (payload) => {
            // Keep specialized UIs for inbox/support list/thread; still refresh home/other pages.
            if (document.querySelector('[data-support-thread]') && payload?.resource === 'support') {
                return;
            }
            if (inboxBody && payload?.resource === 'inbox') {
                return;
            }
            if (supportBody && payload?.resource === 'support') {
                return;
            }
            if (! screenMatches(payload)) {
                return;
            }
            softReload();
        });

    echo.private(`staff.${staffId}`)
        .listen('.staff.inbox.received', (payload) => {
            showBell(Number(payload?.unread_count || 0));

            const row = payload?.notification;
            if (! inboxBody || ! row?.id || inboxBody.querySelector(`[data-inbox-id="${row.id}"]`)) {
                return;
            }

            const empty = inboxBody.querySelector('[data-inbox-empty]');
            empty?.remove();

            const tr = document.createElement('tr');
            tr.setAttribute('data-inbox-id', String(row.id));
            tr.innerHTML = `
                <td><b></b> <span class="badge warn"></span></td>
                <td></td>
                <td class="en"></td>
                <td></td>
            `;
            tr.querySelector('b').textContent = row.title || '';
            tr.querySelector('.badge').textContent = unreadLabel;
            tr.children[1].textContent = row.body || '';
            tr.children[2].textContent = row.created_at
                ? new Date(row.created_at).toISOString().slice(0, 16).replace('T', ' ')
                : '';
            inboxBody.prepend(tr);
        });

    if (desk.getAttribute('data-can-crm') === '1') {
        echo.private('support.desk')
            .listen('.support.desk.updated', (payload) => {
                const thread = payload?.thread;
                if (! thread?.id) {
                    return;
                }

                if (! supportBody) {
                    return;
                }

                let tr = supportBody.querySelector(`[data-thread-id="${thread.id}"]`);
                if (! tr) {
                    const empty = supportBody.querySelector('[data-support-empty]');
                    empty?.remove();
                    tr = document.createElement('tr');
                    tr.setAttribute('data-thread-id', String(thread.id));
                    tr.innerHTML = `
                        <td class="en"><b></b></td>
                        <td class="en"></td>
                        <td>—</td>
                        <td></td>
                        <td><a class="btn ghost sm" href="/dashboard/support/${thread.id}"></a></td>
                    `;
                    supportBody.prepend(tr);
                }

                tr.querySelector('b').textContent = thread.public_code || '';
                tr.children[1].textContent = `USR-${thread.user_id}`;
                tr.children[3].textContent = thread.status || '';
                const link = tr.querySelector('a');
                if (link) {
                    link.textContent = viewLabel;
                    link.setAttribute('href', `/dashboard/support/${thread.id}`);
                }

                if (payload?.action === 'closed') {
                    tr.children[3].textContent = 'closed';
                }
            });
    }
};

document.addEventListener('DOMContentLoaded', bootDashboardRealtime);
