import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

export const getEcho = () => {
    if (echoInstance) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_PUSHER_APP_KEY;
    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu';
    if (! key) {
        return null;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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

    return echoInstance;
};
