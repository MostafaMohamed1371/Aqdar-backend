import { getEcho, subscribePrivate } from './echo';
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
    listings: ['submitted', 'resubmitted', 'returned_to_review', 'reopened', 'in_review'],
    documents: ['uploaded', 'pending', 'document_uploaded'],
    media: ['uploaded', 'pending'],
    leads: ['inquiry', 'created'],
    requests: ['created'],
    cases: ['opened'],
    commissions: ['created', 'recorded'],
    users: [],
    support: [],
    inbox: [],
};

const isNewAction = (resource, action) => {
    const key = String(action || '').replace(/^(listing|lead|request|case)\./, '');

    return (KPI_NEW_ACTIONS[resource] || []).includes(action)
        || (KPI_NEW_ACTIONS[resource] || []).includes(key);
};

const bumpSidebarBadges = (resource, action) => {
    if (! isNewAction(resource, action)) {
        return;
    }

    const map = {
        listings: ['ADM-02'],
        documents: ['ADM-03'],
        media: ['ADM-02', 'ADM-03'],
        leads: ['ADM-07'],
        requests: ['ADM-08'],
        cases: ['ADM-09'],
    };

    (map[resource] || []).forEach((screen) => {
        document.querySelectorAll(`[data-live-badge="${screen}"]`).forEach((node) => {
            const current = Number(String(node.textContent || '0').replace(/[^\d.-]/g, '')) || 0;
            const next = current + 1;
            node.textContent = String(next);
            node.hidden = next < 1;
            flash(node);
        });
    });
};

const applyBadges = (badges) => {
    if (! badges || typeof badges !== 'object') {
        return;
    }

    Object.entries(badges).forEach(([screen, count]) => {
        const next = Number(count) || 0;
        document.querySelectorAll(`[data-live-badge="${screen}"]`).forEach((node) => {
            node.textContent = String(next);
            node.hidden = next < 1;
        });
        document.querySelectorAll(`[data-live-kpi="${screen}"]`).forEach((node) => {
            node.textContent = String(next);
            flash(node);
        });
    });
};

const bumpKpis = (resource, action) => {
    const map = {
        listings: (action === 'listing.published' || action === 'published')
            ? ['total_listings', 'listings_approved']
            : (isNewAction('listings', action) ? ['listings_in_review'] : []),
        documents: (action === 'uploaded' || action === 'pending')
            ? ['media_pending']
            : ((action === 'document_uploaded') ? ['documents_pending'] : []),
        media: (action === 'uploaded' || action === 'pending') ? ['media_pending'] : [],
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

    supportBody.querySelector('[data-support-empty]')?.remove();
    let item = supportBody.querySelector(`[data-thread-id="${thread.id}"]`);
    if (! item) {
        item = document.createElement('a');
        item.className = 'support-thread-item';
        item.setAttribute('data-thread-id', String(thread.id));
        item.setAttribute('href', thread.url || `/dashboard/support/${thread.id}`);
        item.innerHTML = `
            <div class="support-thread-avatar en"></div>
            <div class="support-thread-meta">
                <div class="support-thread-top"><b></b><span class="tiny en"></span></div>
                <div class="support-thread-sub"><span class="en"></span><span class="badge grey"></span></div>
                <p class="support-thread-preview"></p>
            </div>
        `;
        supportBody.prepend(item);
    }

    const name = thread.user_name || `USR-${thread.user_id}`;
    item.querySelector('.support-thread-avatar').textContent = String(name).slice(0, 1).toUpperCase();
    item.querySelector('.support-thread-top b').textContent = name;
    item.querySelector('.support-thread-sub .en').textContent = thread.public_code || '';
    const badge = item.querySelector('.badge');
    badge.textContent = thread.status || '';
    badge.className = `badge ${thread.status === 'open' ? 'ok' : 'grey'}`;
    item.querySelector('.support-thread-preview').textContent = thread.preview || '';
    if (thread.url) {
        item.setAttribute('href', thread.url);
    }
    flash(item);
};

const appendThreadMessage = (state) => {
    const root = document.querySelector('[data-support-thread]');
    const list = root?.querySelector('[data-support-messages]');
    const message = state.lastThreadMessage;
    if (! root || ! list || ! message?.id || list.querySelector(`[data-message-id="${message.id}"]`)) {
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
    name.textContent = fromSales
        ? (root.getAttribute('data-label-sales') || 'Sales')
        : (root.getAttribute('data-label-user') || `USR-${root.getAttribute('data-user-id') || ''}`);
    const time = document.createElement('span');
    time.className = 'tiny en';
    meta.append(name, time);

    const body = document.createElement('div');
    body.className = 'support-bubble-body';
    body.textContent = message.body || '';

    row.append(meta, body);
    list.append(row);
    list.scrollTop = list.scrollHeight;
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

const findLiveTable = (resource) => document.querySelector(`[data-live-table="${resource}"]`);

const LIVE_RESOURCES = ['listings', 'leads', 'requests', 'cases', 'documents', 'media'];

const liveWatermark = Object.fromEntries(LIVE_RESOURCES.map((resource) => [resource, 0]));
const liveBootstrapped = Object.fromEntries(LIVE_RESOURCES.map((resource) => [resource, false]));
const notifiedLiveIds = new Set();
let inboxWatermark = 0;
let inboxBootstrapped = false;
let liveFeedOpened = false;

const feedResourceLabel = (resource) => {
    const map = {
        listings: document.body.getAttribute('data-label-feed-listing') || 'Listing',
        media: document.body.getAttribute('data-label-feed-media') || 'Photo',
        documents: document.body.getAttribute('data-label-feed-document') || 'Document',
        requests: document.body.getAttribute('data-label-feed-request') || 'Buyer request',
        leads: document.body.getAttribute('data-label-feed-lead') || 'Lead',
        cases: document.body.getAttribute('data-label-feed-case') || 'Case',
    };

    return map[resource] || resource;
};

const notificationKey = (resource, rowOrId, status = '') => {
    const id = typeof rowOrId === 'object' && rowOrId !== null
        ? Number(rowOrId.id || 0)
        : Number(rowOrId || 0);
    const rowStatus = typeof rowOrId === 'object' && rowOrId !== null
        ? String(rowOrId.status || status || '')
        : String(status || '');

    // Include status so draft → submitted (same listing id) still notifies the open desk.
    if (resource === 'listings') {
        return `${resource}:${id}:${rowStatus || 'waiting'}`;
    }

    return `${resource}:${id}`;
};

const openLiveFeed = () => {
    const feed = document.querySelector('[data-desk-live-feed]');
    if (! feed) {
        return;
    }
    feed.hidden = false;
    liveFeedOpened = true;
};

const prependLiveFeedItem = (resource, row, isNew = false) => {
    const list = document.querySelector('[data-desk-live-feed-list]');
    const empty = document.querySelector('[data-desk-live-feed-empty]');
    if (! list || ! row?.id) {
        return;
    }

    openLiveFeed();
    empty?.setAttribute('hidden', 'hidden');

    const key = notificationKey(resource, row);
    let item = list.querySelector(`[data-feed-key="${key}"]`);
    if (! item) {
        item = document.createElement('a');
        item.className = 'desk-live-feed__item';
        item.setAttribute('data-feed-key', key);
        item.setAttribute('data-feed-resource', resource);
        item.setAttribute('data-feed-id', String(row.id));
        item.innerHTML = '<div><b></b><span></span></div><span class="badge warn"></span>';
        list.prepend(item);
    }

    item.href = row.url || toastUrl(resource, { row, listing_id: row.listing_id });
    item.querySelector('b').textContent = row.public_code || `#${row.id}`;
    item.querySelector('span').textContent = `${feedResourceLabel(resource)} · ${row.title || row.status_label || row.status || ''}`.trim();
    item.querySelector('.badge').textContent = row.status_label || row.status || feedResourceLabel(resource);
    if (isNew) {
        item.classList.add('is-new');
        flash(item);
        window.setTimeout(() => item.classList.remove('is-new'), 8000);
    }
};

const wireLiveFeedClose = () => {
    document.querySelector('[data-desk-live-feed-close]')?.addEventListener('click', () => {
        const feed = document.querySelector('[data-desk-live-feed]');
        if (feed) {
            feed.hidden = true;
        }
    });
};

const maxLiveId = (resource) => {
    let max = 0;
    document.querySelectorAll(`[data-live-row="${resource}"][data-live-id]`).forEach((node) => {
        max = Math.max(max, Number(node.getAttribute('data-live-id') || 0));
    });

    return max;
};

const maxInboxId = () => {
    let max = 0;
    document.querySelectorAll('[data-inbox-id]').forEach((node) => {
        max = Math.max(max, Number(node.getAttribute('data-inbox-id') || 0));
    });

    return max;
};

const seedLiveWatermarks = () => {
    LIVE_RESOURCES.forEach((resource) => {
        const max = maxLiveId(resource);
        liveWatermark[resource] = max;
        if (max > 0) {
            // Do not mark bootstrapped from DOM alone — first /dashboard/live snapshot
            // owns the baseline so draft→submit and brand-new ads still toast.
            document.querySelectorAll(`[data-live-row="${resource}"][data-live-id]`).forEach((node) => {
                const id = Number(node.getAttribute('data-live-id') || 0);
                const status = node.querySelector('[data-live-field="status"]')?.textContent?.trim() || '';
                notifiedLiveIds.add(notificationKey(resource, { id, status }));
                notifiedLiveIds.add(`${resource}:${id}`);
            });
        }
    });

    inboxWatermark = maxInboxId();
    if (inboxWatermark > 0) {
        inboxBootstrapped = true;
    }
};

const pollActionFor = (resource, row = {}) => {
    const status = String(row.status || '').toLowerCase();

    if (resource === 'listings') {
        return status === 'resubmitted' ? 'resubmitted' : 'submitted';
    }
    if (resource === 'media') {
        return 'uploaded';
    }
    if (resource === 'documents') {
        return 'document_uploaded';
    }
    if (resource === 'leads') {
        return 'created';
    }
    if (resource === 'requests') {
        return 'created';
    }
    if (resource === 'cases') {
        return 'opened';
    }

    return 'submitted';
};

const rememberLiveNotification = (resource, rowOrId, status = '') => {
    const id = typeof rowOrId === 'object' && rowOrId !== null
        ? Number(rowOrId.id || 0)
        : Number(rowOrId || 0);
    const key = notificationKey(resource, rowOrId, status);
    if (! id || notifiedLiveIds.has(key)) {
        return false;
    }
    notifiedLiveIds.add(key);

    return true;
};

const buildLiveRow = (resource, row) => {
    if (! row?.id) {
        return null;
    }

    const tr = document.createElement('tr');
    tr.setAttribute('data-live-row', resource);
    tr.setAttribute('data-live-id', String(row.id));
    tr.className = 'live-new-row';

    const viewLabel = row.view_label || document.body.getAttribute('data-label-view') || 'View';
    const statusTone = row.status_tone || 'warn';
    const statusLabel = row.status_label || row.status || '';
    const code = row.public_code || `#${row.id}`;
    const title = row.title || code;

    if (resource === 'listings') {
        tr.innerHTML = `
            <td></td>
            <td>—</td>
            <td><b></b><div class="tiny en"></div></td>
            <td><span class="badge grey"></span></td>
            <td></td>
            <td></td>
            <td class="en"></td>
            <td>—</td>
            <td><span class="badge ${statusTone}" data-live-field="status"></span></td>
            <td><a class="btn ghost sm" href=""></a></td>
        `;
        tr.querySelector('b').textContent = title;
        tr.querySelector('.tiny').textContent = code;
        tr.children[3].querySelector('.badge').textContent = row.market || '';
        tr.children[4].textContent = row.governorate || '—';
        tr.children[5].textContent = row.developer || '—';
        tr.children[6].textContent = row.created_at || '';
        tr.querySelector('[data-live-field="status"]').textContent = statusLabel;
        const link = tr.querySelector('a');
        link.href = row.url || `/dashboard/listings/${row.id}`;
        link.textContent = viewLabel;
    } else {
        tr.innerHTML = `
            <td><b class="en"></b></td>
            <td></td>
            <td><span class="badge ${statusTone}" data-live-field="status"></span></td>
            <td><a class="btn ghost sm" href=""></a></td>
        `;
        tr.querySelector('b').textContent = code;
        tr.children[1].textContent = title;
        tr.querySelector('[data-live-field="status"]').textContent = statusLabel;
        if (row.stage) {
            const stage = document.createElement('span');
            stage.className = 'badge info';
            stage.setAttribute('data-live-field', 'stage');
            stage.textContent = row.stage_label || row.stage;
            tr.children[2].append(' ', stage);
        }
        const link = tr.querySelector('a');
        link.href = row.url || '#';
        link.textContent = viewLabel;
    }

    return tr;
};

const upsertLiveRow = (resource, row, action) => {
    const table = findLiveTable(resource);
    if (! table || ! row?.id) {
        return false;
    }

    // Never inject debug/ping rows into real moderation tables.
    if (row.ping || String(row.public_code || '').startsWith('PING-')) {
        return false;
    }

    const existing = table.querySelector(`[data-live-row="${resource}"][data-live-id="${row.id}"]`);
    if (existing) {
        if (row.status_label || row.status) {
            patchLiveField(resource, row.id, 'status', row.status_label || row.status);
        }
        if (row.stage_label || row.stage) {
            patchLiveField(resource, row.id, 'stage', row.stage_label || row.stage);
        }
        flash(existing);

        return false;
    }

    if (! isNewAction(resource, action) && action !== 'poll') {
        return false;
    }

    table.querySelector('[data-live-empty]')?.remove();
    const tr = buildLiveRow(resource, row);
    if (! tr) {
        return false;
    }

    const body = table.tBodies?.[0] || table;
    const header = body.querySelector('tr:not([data-live-row])');
    if (header && header.nextSibling) {
        body.insertBefore(tr, header.nextSibling);
    } else {
        body.prepend(tr);
    }
    flash(tr);

    return true;
};

const patchDeskResource = (state) => {
    const resource = state.lastResource;
    const id = state.lastId;
    const meta = state.lastMeta || {};
    if (meta.ping || String(meta.public_code || meta.row?.public_code || '').startsWith('PING-')) {
        return;
    }
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

    if (meta.row && typeof meta.row === 'object') {
        upsertLiveRow(resource, meta.row, state.lastAction || 'poll');
    } else if (isNewAction(resource, state.lastAction)) {
        upsertLiveRow(resource, {
            id,
            public_code: meta.public_code || `#${id}`,
            title: meta.title || meta.public_code || `#${id}`,
            status: meta.status || state.lastAction,
            status_label: meta.label || meta.status || state.lastAction,
            status_tone: 'warn',
            stage: meta.stage,
            url: meta.url || null,
        }, state.lastAction);
    }

    patchLiveField(resource, id, 'status', fieldValue(resource, state.lastAction, meta, 'status'));
    if (meta.stage) {
        patchLiveField(resource, id, 'stage', fieldValue(resource, state.lastAction, meta, 'stage'));
    }
    if (state.lastAction === 'poll') {
        return;
    }

    if (rememberLiveNotification(resource, meta.row || id, meta.status || state.lastAction)) {
        bumpKpis(resource, state.lastAction);
        bumpSidebarBadges(resource, state.lastAction);
        showWaitingToast(resource, state.lastAction, meta);
        if (meta.row || isNewAction(resource, state.lastAction)) {
            prependLiveFeedItem(resource, meta.row || {
                id,
                public_code: meta.public_code || `#${id}`,
                title: meta.title || meta.public_code || `#${id}`,
                status: meta.status || state.lastAction,
                status_label: meta.label || meta.status || state.lastAction,
                url: meta.url || toastUrl(resource, meta),
                listing_id: meta.listing_id,
            }, true);
        }
    }
};

const toastTemplate = (resource, action) => {
    if (resource === 'listings' && isNewAction(resource, action)) {
        return document.body.getAttribute('data-toast-listing') || 'New listing awaiting review: :code';
    }
    if (resource === 'media' && (action === 'uploaded' || action === 'pending')) {
        return document.body.getAttribute('data-toast-media') || 'New public photo awaiting approval: :code';
    }
    if (resource === 'documents' && action === 'document_uploaded') {
        return document.body.getAttribute('data-toast-document') || 'New document awaiting review: :code';
    }
    if (resource === 'documents' && (action === 'uploaded' || action === 'pending')) {
        return document.body.getAttribute('data-toast-media') || 'New public photo awaiting approval: :code';
    }
    if (resource === 'leads' && isNewAction(resource, action)) {
        return document.body.getAttribute('data-toast-lead') || 'New lead waiting: :code';
    }
    if (resource === 'requests' && isNewAction(resource, action)) {
        return document.body.getAttribute('data-toast-request') || 'New buyer request: :code';
    }
    if (resource === 'cases' && isNewAction(resource, action)) {
        return document.body.getAttribute('data-toast-case') || 'New transfer case: :code';
    }

    return null;
};

const toastUrl = (resource, meta) => {
    if (meta?.row?.url) {
        return meta.row.url;
    }
    if (resource === 'listings' && meta?.listing_id) {
        return `/dashboard/listings/${meta.listing_id}`;
    }
    if ((resource === 'documents' || resource === 'media') && meta?.listing_id) {
        return `/dashboard/listings/${meta.listing_id}`;
    }
    if (resource === 'listings') {
        return '/dashboard/listings?status=in_review';
    }
    if (resource === 'leads') {
        return '/dashboard/leads';
    }
    if (resource === 'requests') {
        return '/dashboard/requests';
    }
    if (resource === 'cases') {
        return '/dashboard/cases';
    }

    return '/dashboard';
};

const showWaitingToast = (resource, action, meta = {}) => {
    const template = toastTemplate(resource, action);
    const stack = document.querySelector('[data-desk-toasts]');
    if (! template || ! stack) {
        return;
    }

    const code = meta.public_code || meta.row?.public_code || meta.listing_code || `#${meta.listing_id || ''}`;
    const text = template.replace(':code', code);
    const toast = document.createElement('div');
    toast.className = 'desk-toast';
    toast.innerHTML = `
        <div class="desk-toast__body">
            <span class="badge warn"></span>
            <div>
                <b></b>
                <p></p>
            </div>
        </div>
        <a class="btn sm" href=""></a>
    `;
    toast.querySelector('.badge').textContent = document.body.getAttribute('data-toast-waiting') || 'Waiting';
    toast.querySelector('b').textContent = code;
    toast.querySelector('p').textContent = text;
    const link = toast.querySelector('a');
    link.href = toastUrl(resource, meta);
    link.textContent = document.body.getAttribute('data-toast-open') || 'Open';
    stack.prepend(toast);
    flash(toast);
    window.setTimeout(() => toast.remove(), 12000);
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
    window.aqdarDeskDebug = () => ({
        liveUrl: deskLiveUrl(),
        pollOk: document.body.getAttribute('data-desk-live-ok') || null,
        pusherState: document.body.getAttribute('data-pusher-state') || 'unknown',
        echo: Boolean(getEcho()),
        watermarks: { ...liveWatermark },
        notified: [...notifiedLiveIds],
    });
    seedLiveWatermarks();
    wireLiveFeedClose();

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

    // Queues + inbox keep working via polling even when Pusher/Echo is unavailable.
    startDeskPolling(cubit);
    startSupportPolling(cubit);

    // Pusher primary path — poll above remains the safety net when auth/WS fails.
    if (! getEcho()) {
        document.body.setAttribute('data-pusher-state', 'offline');
        console.warn('aqdar.desk_pusher_offline', 'Polling /dashboard/live every 2s');

        return;
    }

    subscribePrivate('desk', {
        '.desk.updated': (payload) => {
            cubit.emit({
                lastResource: payload?.resource || null,
                lastAction: payload?.action || null,
                lastId: payload?.id ?? null,
                lastScreens: Array.isArray(payload?.screens) ? payload.screens : [],
                lastMeta: payload?.meta || {},
            });
        },
    });

    subscribePrivate(`staff.${staffId}`, {
        '.staff.inbox.received': (payload) => {
            cubit.emit({
                unreadCount: Number(payload?.unread_count || 0),
                lastInbox: payload?.notification || null,
            });
        },
    });

    if (desk.getAttribute('data-can-crm') === '1') {
        subscribePrivate('support.desk', {
            '.support.desk.updated': (payload) => {
                cubit.emit({
                    lastSupport: payload?.thread || null,
                    lastAction: payload?.action || 'message',
                });
            },
        });
    }

    const threadRoot = document.querySelector('[data-support-thread]');
    const threadId = threadRoot?.getAttribute('data-support-thread');
    if (threadId) {
        subscribePrivate(`support.thread.${threadId}`, {
            '.support.message.sent': (payload) => {
                cubit.emit({
                    lastThreadMessage: payload?.message || null,
                });
            },
            '.support.thread.closed': () => {
                cubit.emit({
                    lastThreadClosed: true,
                });
            },
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

const deskLiveUrl = () => {
    const raw = document.body.getAttribute('data-desk-live') || '';
    if (! raw) {
        return '';
    }

    // Always same-origin relative path so APP_URL=localhost does not break LAN / 127.0.0.1 desks.
    try {
        if (raw.startsWith('http://') || raw.startsWith('https://')) {
            return new URL(raw).pathname;
        }
    } catch (error) {
        console.warn('aqdar.desk_live_url_invalid', raw, error);
    }

    return raw.startsWith('/') ? raw : `/${raw}`;
};

const pollDeskQueues = async (cubit) => {
    const url = deskLiveUrl();
    if (! url) {
        return;
    }

    const params = new URLSearchParams({
        inbox_after: String(inboxBootstrapped ? Math.max(inboxWatermark, maxInboxId()) : 0),
    });

    try {
        const response = await fetch(`${url}?${params.toString()}`, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });
        const contentType = response.headers.get('content-type') || '';
        if (! response.ok || ! contentType.includes('application/json')) {
            console.warn('aqdar.desk_live_poll_http', response.status, url, contentType);

            return;
        }

        const payload = await response.json();
        const data = payload?.data || {};
        applyBadges(data.badges);
        applyBadges(data.needs);

        LIVE_RESOURCES.forEach((resource) => {
            const rows = (data[resource] || []).filter((row) => row?.id);

            if (! liveBootstrapped[resource]) {
                rows.forEach((row) => {
                    notifiedLiveIds.add(notificationKey(resource, row));
                    liveWatermark[resource] = Math.max(liveWatermark[resource] || 0, Number(row.id) || 0);
                    upsertLiveRow(resource, row, 'poll');
                });
                liveBootstrapped[resource] = true;

                return;
            }

            const activeKeys = new Set(rows.map((row) => notificationKey(resource, row)));
            [...notifiedLiveIds].forEach((key) => {
                if (! key.startsWith(`${resource}:`)) {
                    return;
                }
                // Drop stale keys for ids no longer waiting (draft→submit uses a new status key).
                const stillActive = [...activeKeys].some((active) => active === key)
                    || rows.some((row) => key === `${resource}:${row.id}` || key.startsWith(`${resource}:${row.id}:`));
                if (! stillActive) {
                    notifiedLiveIds.delete(key);
                }
            });

            rows.forEach((row) => {
                const id = Number(row.id);
                const key = notificationKey(resource, row);
                const isNew = ! notifiedLiveIds.has(key);
                const action = isNew ? pollActionFor(resource, row) : 'poll';
                upsertLiveRow(resource, row, action);
                liveWatermark[resource] = Math.max(liveWatermark[resource] || 0, id);

                if (! isNew || ! rememberLiveNotification(resource, row)) {
                    return;
                }

                bumpKpis(resource, action);
                bumpSidebarBadges(resource, action);
                prependLiveFeedItem(resource, row, true);
                showWaitingToast(resource, action, {
                    row,
                    status: row.status,
                    public_code: row.public_code,
                    listing_id: row.listing_id || (resource === 'listings' ? id : null),
                });
            });
        });

        const inbox = data.inbox || {};
        const unreadCount = Number(inbox.unread_count);
        const hasUnreadCount = inbox.unread_count !== undefined && inbox.unread_count !== null && ! Number.isNaN(unreadCount);
        if (hasUnreadCount) {
            cubit.emit({ unreadCount });
        }

        const inboxItems = (inbox.items || [])
            .filter((item) => item?.id)
            .sort((left, right) => Number(left.id) - Number(right.id));
        const inboxCursor = Number(data.cursors?.inbox || 0);

        if (! inboxBootstrapped) {
            const maxFromItems = inboxItems.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0);
            inboxWatermark = Math.max(inboxWatermark, inboxCursor, maxFromItems, maxInboxId());
            inboxBootstrapped = true;
        } else {
            inboxItems.forEach((item) => {
                const id = Number(item.id);
                if (id <= inboxWatermark) {
                    return;
                }
                inboxWatermark = id;
                const patch = { lastInbox: item };
                if (hasUnreadCount) {
                    patch.unreadCount = unreadCount;
                }
                cubit.emit(patch);
            });

            if (inboxCursor > inboxWatermark) {
                inboxWatermark = inboxCursor;
            }
        }

        document.body.setAttribute('data-desk-live-ok', data.polled_at || '1');
    } catch (error) {
        console.warn('aqdar.desk_live_poll_failed', error);
    }
};

const startDeskPolling = (cubit) => {
    if (! deskLiveUrl()) {
        console.warn('aqdar.desk_live_missing', 'body[data-desk-live] is empty');

        return;
    }

    const tick = () => {
        // Keep polling while the desk tab is open so mobile advertiser submits appear live.
        pollDeskQueues(cubit);
    };

    tick();
    window.setInterval(tick, 2000);
    document.addEventListener('visibilitychange', () => {
        pollDeskQueues(cubit);
    });
};

// Vite loads this as a deferred module — DOMContentLoaded may already have fired.
const ready = (fn) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
};

ready(bootDashboardCubit);
