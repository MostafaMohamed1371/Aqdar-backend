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

        return null;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
            },
        });
    } catch (error) {
        echoUnavailable = true;
        console.warn('aqdar.echo_unavailable', error);

        return null;
    }

    return echoInstance;
};
