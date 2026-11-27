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
        document.body?.setAttribute('data-pusher-state', 'offline');
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
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    fetch('/broadcasting/auth', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                        .then(async (response) => {
                            if (! response.ok) {
                                const text = await response.text();
                                console.warn('aqdar.echo_auth_failed', response.status, channel.name, text.slice(0, 200));
                                callback(new Error(`auth ${response.status}`), null);

                                return;
                            }

                            callback(null, await response.json());
                        })
                        .catch((error) => {
                            console.warn('aqdar.echo_auth_error', channel.name, error);
                            callback(error, null);
                        });
                },
            }),
        });

        const pusher = echoInstance.connector?.pusher;
        if (pusher?.connection) {
            pusher.connection.bind('connected', () => {
                document.body?.setAttribute('data-pusher-state', 'connected');
            });
            pusher.connection.bind('error', (error) => {
                console.warn('aqdar.pusher_connection_error', error);
            });
            pusher.connection.bind('state_change', (states) => {
                document.body?.setAttribute('data-pusher-state', states?.current || '');
            });
        }
    } catch (error) {
        echoUnavailable = true;
        document.body?.setAttribute('data-pusher-state', 'offline');
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
