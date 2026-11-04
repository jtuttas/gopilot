/// <reference lib="webworker" />
// GoPilot service worker (vite-plugin-pwa, injectManifest strategy):
// precaches the app shell and shows web push notifications from the plugin.
import { clientsClaim } from 'workbox-core'
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching'
import { NavigationRoute, registerRoute } from 'workbox-routing'

declare let self: ServiceWorkerGlobalScope

self.skipWaiting()
clientsClaim()
cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST)

// SPA: every in-app navigation is served by the cached index.html.
registerRoute(new NavigationRoute(createHandlerBoundToURL('index.html')))

interface PushPayload {
  title?: string
  body?: string
  /** Alternative field name for the body. */
  message?: string
  url?: string
  data?: { url?: string }
}

function parsePayload(event: PushEvent): PushPayload {
  if (!event.data) return {}
  try {
    return event.data.json() as PushPayload
  } catch {
    return { body: event.data.text() }
  }
}

// The app may live below a path (e.g. /gopilot/ on GitHub Pages); the scope ends with '/'.
const appBase = self.registration.scope

/** App path from the plugin (e.g. '/woche/2026-11-02') -> absolute URL inside the app scope. */
function appUrl(path: string): string {
  return new URL(path.replace(/^\/+/, ''), appBase).href
}

self.addEventListener('push', (event) => {
  const payload = parsePayload(event)
  const url = payload.url ?? payload.data?.url ?? '/'
  event.waitUntil(
    self.registration.showNotification(payload.title || 'GoPilot', {
      body: payload.body ?? payload.message ?? '',
      icon: appUrl('icons/icon-192.png'),
      badge: appUrl('icons/badge-96.png'),
      lang: 'de',
      data: { url },
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const target = appUrl((event.notification.data as { url?: string } | null)?.url ?? '/')
  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      for (const client of windows) {
        if (new URL(client.url).origin === self.location.origin) {
          await client.focus()
          if ('navigate' in client) await client.navigate(target)
          return
        }
      }
      await self.clients.openWindow(target)
    })(),
  )
})
