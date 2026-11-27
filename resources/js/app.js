import { initThemeToggle } from './theme';
import './dashboard-realtime';

const ready = (fn) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
};

ready(() => {
    initThemeToggle();

    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButton = document.querySelector('[data-sidebar-close]');

    const openSidebar = () => {
        sidebar?.classList.add('is-open');
        backdrop?.classList.add('is-open');
        backdrop?.removeAttribute('hidden');
        document.body.classList.add('sidebar-open');
        openButton?.setAttribute('aria-expanded', 'true');
    };

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        backdrop?.classList.remove('is-open');
        backdrop?.setAttribute('hidden', 'hidden');
        document.body.classList.remove('sidebar-open');
        openButton?.setAttribute('aria-expanded', 'false');
    };

    openButton?.addEventListener('click', openSidebar);
    closeButton?.addEventListener('click', closeSidebar);
    backdrop?.addEventListener('click', closeSidebar);
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            closeSidebar();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    if (sidebar) {
        const scrollKey = 'aqdar.dside.scrollTop';

        const persistSidebarScroll = () => {
            sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
        };

        const restoreSidebarScroll = () => {
            const saved = sessionStorage.getItem(scrollKey);

            if (saved !== null) {
                sidebar.scrollTop = Number(saved) || 0;
            }

            const active = sidebar.querySelector('a.on');

            if (! active) {
                return;
            }

            const pane = sidebar.getBoundingClientRect();
            const item = active.getBoundingClientRect();

            if (item.top < pane.top || item.bottom > pane.bottom) {
                sidebar.scrollTop += item.top - pane.top - (pane.height / 2) + (item.height / 2);
            }
        };

        sidebar.addEventListener('scroll', persistSidebarScroll, { passive: true });
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', persistSidebarScroll);
        });
        window.addEventListener('pagehide', persistSidebarScroll);
        restoreSidebarScroll();
    }

    document.querySelectorAll('[data-locale-dropdown]').forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-locale-toggle]');
        const menu = dropdown.querySelector('[data-locale-menu]');
        const chevron = dropdown.querySelector('[data-locale-chevron]');

        if (! toggle || ! menu) {
            return;
        }

        const close = () => {
            menu.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
            chevron?.classList.remove('rotate-180');
        };

        const open = () => {
            menu.classList.remove('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            chevron?.classList.add('rotate-180');
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();

            if (menu.classList.contains('hidden')) {
                open();
            } else {
                close();
            }
        });

        document.addEventListener('click', (event) => {
            if (! dropdown.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    document.querySelectorAll('[data-profile-photo]').forEach((form) => {
        const input = form.querySelector('input[type="file"][name="photo"]');
        const nameLabel = form.querySelector('[data-profile-photo-name]');
        const remove = form.querySelector('input[name="remove_photo"]');
        const previews = form.querySelectorAll('[data-profile-photo-preview]');

        if (! input) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files?.[0];

            if (! file || ! file.type.startsWith('image/')) {
                return;
            }

            if (remove) {
                remove.checked = false;
            }

            if (nameLabel) {
                nameLabel.textContent = file.name;
            }

            const url = URL.createObjectURL(file);

            previews.forEach((preview) => {
                const img = document.createElement('img');
                img.className = preview.getAttribute('data-profile-photo-preview') === 'hero' ? 'av xl' : 'av l';
                img.src = url;
                img.alt = '';
                img.width = preview.getAttribute('data-profile-photo-preview') === 'hero' ? 84 : 72;
                img.height = img.width;
                preview.replaceChildren(img);
            });
        });
    });

    document.querySelectorAll('[data-flash-dismiss]').forEach((button) => {
        button.addEventListener('click', () => {
            const flash = button.closest('[data-flash]');

            if (! flash) {
                return;
            }

            flash.setAttribute('hidden', 'hidden');
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const wrapper = button.closest('.inp, .relative');
        const input = wrapper?.querySelector('[data-password-input]');
        const showIcon = button.querySelector('[data-password-icon-show]');
        const hideIcon = button.querySelector('[data-password-icon-hide]');

        if (! input || ! showIcon || ! hideIcon) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                isHidden ? button.dataset.labelHide : button.dataset.labelShow,
            );
            showIcon.classList.toggle('hidden', isHidden);
            hideIcon.classList.toggle('hidden', ! isHidden);
        });
    });
});
