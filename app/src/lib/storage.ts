// Small localStorage wrapper. Storage can be unavailable (private mode,
// blocked site data), so every access is guarded.

const TOKEN_KEY = 'gopilot.token'

function read(key: string): string | null {
  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string | null): void {
  try {
    if (value === null) window.localStorage.removeItem(key)
    else window.localStorage.setItem(key, value)
  } catch {
    // ignore: the session then simply does not survive a reload
  }
}

/** Only the web service token is stored, never the password (ADR 0006). */
export const tokenStore = {
  get: (): string | null => read(TOKEN_KEY),
  set: (token: string): void => write(TOKEN_KEY, token),
  clear: (): void => write(TOKEN_KEY, null),
}
