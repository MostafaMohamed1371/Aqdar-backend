import { getEcho } from './echo';
import { createDeskCubit } from './desk-cubit';

const STATUS_LABELS = {
    listings: {
        submitted: 'Submitted',
        in_review: 'In review',
        resubmitted: 'Resubmitted',
        needs_changes: 'Needs changes',
        approved: 'Approved',
        published: 'Published',
        hold: 'Hold',
        rejected: 'Rejected',
        unpublished: 'Unpublished',
        archived: 'Archived',
        closed: 'Closed',
        draft: 'Draft',
    },
    documents: {
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected',
        reviewed: 'Reviewed',
        media_reviewed: 'Reviewed',
    },
    users: {
        suspended: 'Suspended',
        reactivated: 'Active',
    },
    commissions: {
        approved: 'Approved',
        paid: 'Paid',
        due: 'Due',
    },
};

const flash = (el) => {
    if (! el) {
        return;
    }
    el.style.transition = 'background-color .8s ease';
    el.style.backgroundColor = 'var(--gold-soft, #f6e7c8)';
    window.setTimeout(() => {
        el.style.backgroundColor = '';
    }, 1200);
};

const seenDeskEvents = new Set();

const KPI_NEW_ACTIONS = {
    listings: ['submitted', 'resubmitted'],
    documents: [],
    leads: ['inquiry', 'created'],
    requests: ['created'],
    cases: ['opened'],
    commissions: ['created', 'recorded'],
    users: [],
    support: [],
    inbox: [],
};

const bumpKpis = (resource, action) => {
    const map = {
        listings: (action === 'listing.published' || action === 'published')
            ? ['total_listings', 'listings_approved']
            : ((action === 'submitted' || action === 'resubmitted') ? ['listings_in_review'] : []),
        documents: [],
        leads: KPI_NEW_ACTIONS.leads.includes(action) ? ['unassigned_leads', 'inquiries_in_range'] : [],
        requests: KPI_NEW_ACTIONS.requests.includes(action) ? ['custom_buy_requests', 'new_buyer_requests'] : [],
        cases: KPI_NEW_ACTIONS.cases.includes(action) ? ['active_transfers'] : [],
        commissions: KPI_NEW_ACTIONS.commissions.includes(action) ? ['recorded_commissions'] : [],
        users: [],
        support: [],
        inbox: [],
    };
    (map[resource] || []).forEach((key) => {
        document.querySelectorAll(`[data-live-kpi="${key}"]`).forEach((node) => {
            const current = Number(String(node.textContent || '0').replace(/[^\d.-]/g, '')) || 0;
            node.textContent = String(current + 1);
            flash(node);
        });
    });
};

const deskEventKey = (state) => [
    state.lastResource || '',
    state.lastAction || '',
    state.lastId ?? '',
    JSON.stringify(state.lastMeta || {}),
].join('|');

const patchLiveField = (resource, id, field, value) => {
    if (! resource || ! id || ! value) {
        return;
    }

    const nodes = document.querySelectorAll(
        `[data-live-row="${resource}"][data-live-id="${id}"] [data-live-field="${field}"], [data-live-row="${resource}"][data-live-id="${id}"][data-live-field="${field}"]`,
    );

    nodes.forEach((node) => {
        node.textContent = value;
        flash(node.closest('tr') || node);
    });
};

const applyInboxBell = (state) => {
    const bell = document.querySelector('[data-inbox-bell]');
    const dot = document.querySelector('[data-inbox-dot]');
    if (! bell || ! dot) {
        return;
    }

    const count = Number(state.unreadCount || 0);
    bell.setAttribute('data-unread', String(count));
    document.body.setAttribute('data-unread', String(count));
    dot.hidden = count < 1;
};

const prependInboxRow = (state) => {
    const inboxBody = document.querySelector('[data-inbox-rows]');
    const row = state.lastInbox;
    if (! inboxBody || ! row?.id || inboxBody.querySelector(`[data-inbox-id="${row.id}"]`)) {
        return;
    }

    inboxBody.querySelector('[data-inbox-empty]')?.remove();

    const unreadLabel = document.body.getAttribute('data-label-unread') || 'Unread';
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
    flash(tr);
};

const upsertSupportRow = (state) => {
    const supportBody = document.querySelector('[data-support-rows]');
    const thread = state.lastSupport;
    if (! supportBody || ! thread?.id) {
        return;
    }

    const viewLabel = document.body.getAttribute('data-label-view') || 'View';
    let tr = supportBody.querySelector(`[data-thread-id="${thread.id}"]`);
    if (! tr) {
        supportBody.querySelector('[data-support-empty]')?.remove();
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
    flash(tr);
};

const appendThreadMessage = (state) => {
    const root = document.querySelector('[data-support-thread]');
    const list = root?.querySelector('[data-support-messages]');
    const message = state.lastThreadMessage;
    if (! root || ! list || ! message?.id || list.querySelector(`[data-message-id="${message.id}"]`)) {
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
    flash(row);
};

const markThreadClosed = () => {
    const root = document.querySelector('[data-support-thread]');
    if (! root) {
        return;
    }
    const form = root.querySelector('form');
    form?.setAttribute('hidden', 'hidden');
    flash(root);
};

const fieldValue = (resource, action, meta, field) => {
    if (field === 'stage') {
        return meta.stage || meta.label || '';
    }
    if (field === 'status') {
        const actionKey = String(action || '').replace(/^(listing|lead|request|case)\./, '');

        return meta.status
            || meta.label
            || STATUS_LABELS[resource]?.[action]
            || STATUS_LABELS[resource]?.[actionKey]
            || action
            || '';
    }

    return meta[field] || meta.label || '';
};

const patchDeskResource = (state) => {
    const resource = state.lastResource;
    const id = state.lastId;
    const meta = state.lastMeta || {};
    const fingerprint = deskEventKey(state);
    if (fingerprint !== '|||{}' && seenDeskEvents.has(fingerprint)) {
        return;
    }
    if (fingerprint !== '|||{}') {
        seenDeskEvents.add(fingerprint);
    }
    if (! resource || ! id) {
        bumpKpis(resource, state.lastAction);

        return;
    }

    patchLiveField(resource, id, 'status', fieldValue(resource, state.lastAction, meta, 'status'));
    if (meta.stage) {
        patchLiveField(resource, id, 'stage', fieldValue(resource, state.lastAction, meta, 'stage'));
    }
    bumpKpis(resource, state.lastAction);
};

const bootDashboardCubit = () => {
    const desk = document.querySelector('[data-staff-id]');
    if (! desk) {
        return;
    }

    const staffId = desk.getAttribute('data-staff-id');
    if (! staffId) {
        return;
    }

    const cubit = createDeskCubit(desk);
    window.deskCubit = cubit;

    cubit.listen((state, patch) => {
        applyInboxBell(state);

        if (patch.lastInbox) {
            prependInboxRow(state);
        }
        if (patch.lastSupport) {
            upsertSupportRow(state);
        }
        if (patch.lastThreadMessage) {
            appendThreadMessage(state);
        }
        if (patch.lastThreadClosed) {
            markThreadClosed();
        }
        if (patch.lastResource) {
            patchDeskResource(state);
        }
    });

    // Chat keeps working via polling even when Pusher/Echo is unavailable on the host.
    startSupportPolling(cubit);

    const echo = getEcho();
    if (! echo) {
        return;
    }

    echo.private('desk')
        .listen('.desk.updated', (payload) => {
            cubit.emit({
                lastResource: payload?.resource || null,
                lastAction: payload?.action || null,
                lastId: payload?.id ?? null,
                lastScreens: Array.isArray(payload?.screens) ? payload.screens : [],
                lastMeta: payload?.meta || {},
            });
        });

    echo.private(`staff.${staffId}`)
        .listen('.staff.inbox.received', (payload) => {
            cubit.emit({
                unreadCount: Number(payload?.unread_count || 0),
                lastInbox: payload?.notification || null,
            });
        });

    if (desk.getAttribute('data-can-crm') === '1') {
        echo.private('support.desk')
            .listen('.support.desk.updated', (payload) => {
                cubit.emit({
                    lastSupport: payload?.thread || null,
                    lastAction: payload?.action || 'message',
                });
            });
    }

    const threadRoot = document.querySelector('[data-support-thread]');
    const threadId = threadRoot?.getAttribute('data-support-thread');
    if (threadId) {
        echo.private(`support.thread.${threadId}`)
            .listen('.support.message.sent', (payload) => {
                cubit.emit({
                    lastThreadMessage: payload?.message || null,
                });
            })
            .listen('.support.thread.closed', () => {
                cubit.emit({
                    lastThreadClosed: true,
                });
            });
    }
};

const csrfHeaders = () => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
});

const latestMessageId = () => {
    const nodes = document.querySelectorAll('[data-support-messages] [data-message-id]');
    let max = 0;
    nodes.forEach((node) => {
        max = Math.max(max, Number(node.getAttribute('data-message-id') || 0));
    });

    return max;
};

const pollSupportThread = async (cubit) => {
    const root = document.querySelector('[data-support-thread]');
    const url = root?.getAttribute('data-support-poll');
    if (! root || ! url) {
        return;
    }

    try {
        const response = await fetch(`${url}?after_id=${latestMessageId()}`, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });
        if (! response.ok) {
            return;
        }

        const payload = await response.json();
        const data = payload?.data || {};
        if (data.status === 'closed') {
            cubit.emit({ lastThreadClosed: true });
        }

        (data.messages || []).forEach((message) => {
            cubit.emit({ lastThreadMessage: message });
        });
    } catch (error) {
        console.warn('aqdar.support_poll_failed', error);
    }
};

const pollSupportIndex = async (cubit) => {
    const body = document.querySelector('[data-support-rows]');
    const url = body?.getAttribute('data-support-poll-index');
    if (! body || ! url) {
        return;
    }

    try {
        const response = await fetch(url, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });
        if (! response.ok) {
            return;
        }

        const payload = await response.json();
        (payload?.data || []).forEach((thread) => {
            cubit.emit({
                lastSupport: thread,
                lastAction: 'message',
            });
        });
    } catch (error) {
        console.warn('aqdar.support_index_poll_failed', error);
    }
};

const startSupportPolling = (cubit) => {
    const hasThread = Boolean(document.querySelector('[data-support-poll]'));
    const hasIndex = Boolean(document.querySelector('[data-support-poll-index]'));
    if (! hasThread && ! hasIndex) {
        return;
    }

    const tick = () => {
        if (document.hidden) {
            return;
        }
        if (hasThread) {
            pollSupportThread(cubit);
        }
        if (hasIndex) {
            pollSupportIndex(cubit);
        }
    };

    tick();
    window.setInterval(tick, 3000);
};

document.addEventListener('DOMContentLoaded', bootDashboardCubit);
