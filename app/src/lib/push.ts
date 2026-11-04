// Web push subscription via the app's service worker (ADR 0005).
import { api } from '../api/functions'
import { MoodleError } from '../api/client'

export function isPushSupported(): boolean {
  return (
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  )
}

/** VAPID public key (Base64-URL) -> bytes for PushManager.subscribe. */
export function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  const out = new Uint8Array(new ArrayBuffer(raw.length))
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i)
  return out
}

async function registration(): Promise<ServiceWorkerRegistration> {
  const reg = await navigator.serviceWorker.getRegistration()
  if (!reg) {
    throw new MoodleError(
      'Benachrichtigungen gehen nur in der installierten bzw. veröffentlichten App (kein Service Worker aktiv).',
      'noserviceworker',
    )
  }
  return reg
}

export async function getPushSubscription(): Promise<PushSubscription | null> {
  if (!isPushSupported()) return null
  const reg = await navigator.serviceWorker.getRegistration()
  return reg ? reg.pushManager.getSubscription() : null
}

/** Asks for permission, subscribes and registers the endpoint in Moodle. */
export async function enablePush(): Promise<void> {
  if (!isPushSupported()) {
    throw new MoodleError('Dieser Browser unterstützt keine Push-Benachrichtigungen.', 'nopush')
  }
  const config = await api.getPushConfig()
  if (!config.enabled || !config.publickey) {
    throw new MoodleError('Push-Benachrichtigungen sind in Moodle noch nicht eingerichtet.', 'pushdisabled')
  }
  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    throw new MoodleError(
      'Benachrichtigungen sind für GoPilot blockiert. Du kannst sie in den Browser-Einstellungen erlauben.',
      'permissiondenied',
    )
  }
  const reg = await registration()
  const sub =
    (await reg.pushManager.getSubscription()) ??
    (await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(config.publickey),
    }))
  const json = sub.toJSON()
  await api.registerPush(sub.endpoint, json.keys?.p256dh ?? '', json.keys?.auth ?? '')
}

/** Unregisters in Moodle and drops the browser subscription. */
export async function disablePush(): Promise<void> {
  const sub = await getPushSubscription()
  if (!sub) return
  try {
    await api.unregisterPush(sub.endpoint)
  } finally {
    await sub.unsubscribe()
  }
}

/** iOS shows web push only for installed PWAs. */
export function isIos(): boolean {
  return /iphone|ipad|ipod/i.test(navigator.userAgent)
}

export function isStandalone(): boolean {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true
  )
}
