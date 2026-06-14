import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo?: Echo<'reverb'>
  }
}

window.Pusher = Pusher

function resolveApiOrigin(): string {
  const apiUrl = import.meta.env.VITE_API_URL as string | undefined
  if (apiUrl) {
    return apiUrl.replace(/\/api\/v1\/?$/, '')
  }
  if (typeof window !== 'undefined') {
    const { port } = window.location
    if (port === '3000' || port === '') {
      return 'http://127.0.0.1:8000'
    }
    return window.location.origin
  }
  return 'http://127.0.0.1:8000'
}

export function isEchoConfigured(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

export function createEcho(bearerToken: string | null): Echo<'reverb'> | null {
  if (!isEchoConfigured() || !bearerToken) {
    return null
  }

  const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http'
  const host = (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? '127.0.0.1'
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)
  const apiOrigin = resolveApiOrigin()

  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY as string,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiOrigin}/api/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${bearerToken}`,
        Accept: 'application/json',
      },
    },
  })
}

export type EchoInstance = Echo<'reverb'>
