import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * Which broadcaster to talk to is picked at BUILD time via VITE_BROADCAST_CONNECTION,
 * not read at runtime — Vite inlines import.meta.env values into the compiled
 * bundle. Local dev builds against Reverb (self-hosted, needs `reverb:start`);
 * a shared-hosting deploy — where you can't run a persistent process or open a
 * custom port — builds against Pusher (a managed WebSocket service) instead.
 * Nothing else in the app (broadcast events, channel auth) changes between the two.
 */
const connection = import.meta.env.VITE_BROADCAST_CONNECTION ?? 'reverb';

window.Echo = new Echo(
    connection === 'pusher'
        ? {
              broadcaster: 'pusher',
              key: import.meta.env.VITE_PUSHER_APP_KEY,
              cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
              forceTLS: true,
          }
        : {
              broadcaster: 'reverb',
              key: import.meta.env.VITE_REVERB_APP_KEY,
              wsHost: import.meta.env.VITE_REVERB_HOST,
              wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
              wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
              forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
              enabledTransports: ['ws', 'wss'],
          }
);
