/**
 * Dashboard Cubit — one shared live state for every staff page.
 * Pusher events emit() into this store; pages listen() and refresh dynamically.
 */
export class DeskCubit {
    constructor(state = {}) {
        this.state = { ...state };
        this.listeners = new Set();
        this.generation = 0;
    }

    getState() {
        return this.state;
    }

    listen(fn) {
        this.listeners.add(fn);

        return () => this.listeners.delete(fn);
    }

    emit(patch) {
        this.generation += 1;
        this.state = {
            ...this.state,
            ...patch,
            revision: this.generation,
            lastEventAt: Date.now(),
        };
        this.listeners.forEach((fn) => fn(this.state, patch));
    }
}

export const createDeskCubit = (desk) => {
    const unread = Number(desk.getAttribute('data-unread') || '0');

    return new DeskCubit({
        staffId: desk.getAttribute('data-staff-id') || '',
        canCrm: desk.getAttribute('data-can-crm') === '1',
        screen: document.querySelector('[data-screen]')?.getAttribute('data-screen') || '',
        unreadCount: Number.isFinite(unread) ? unread : 0,
        lastResource: null,
        lastAction: null,
        lastId: null,
        lastScreens: [],
        lastInbox: null,
        lastSupport: null,
        lastThreadMessage: null,
        lastThreadClosed: false,
        lastMeta: {},
        revision: 0,
        lastEventAt: 0,
    });
};
