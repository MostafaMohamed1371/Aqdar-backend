import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;
let echoUnavailable = false;

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';

export const getEcho = () => {
    if (echoInstance) {
        return echoInstance;
    }

    if (echoUnavailable) {
        return null;
    }

    const driver = meta('broadcast-driver') || 'pusher';
    const key = meta('pusher-key') || import.meta.env.VITE_PUSHER_APP_KEY || '';
    const cluster = meta('pusher-cluster') || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu';

    if (driver !== 'pusher' || ! key) {
        echoUnavailable = true;
        console.warn('aqdar.echo_unavailable', { driver, hasKey: Boolean(key) });

        return null;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster,
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        const pusher = echoInstance.connector?.pusher;
        if (pusher?.connection) {
            pusher.connection.bind('error', (error) => {
                console.warn('aqdar.pusher_connection_error', error);
            });
            pusher.connection.bind('state_change', (states) => {
                document.body?.setAttribute('data-pusher-state', states?.current || '');
            });
        }
    } catch (error) {
        echoUnavailable = true;
        console.warn('aqdar.echo_unavailable', error);

        return null;
    }

    return echoInstance;
};

export const subscribePrivate = (channelName, bindings = {}) => {
    const echo = getEcho();
    if (! echo) {
        return null;
    }

    const channel = echo.private(channelName);

    try {
        if (typeof channel.error === 'function') {
            channel.error((error) => {
                console.warn('aqdar.echo_channel_error', channelName, error);
            });
        }
    } catch (error) {
        console.warn('aqdar.echo_channel_bind_failed', channelName, error);
    }

    Object.entries(bindings).forEach(([event, handler]) => {
        channel.listen(event, handler);
    });

    return channel;
};
