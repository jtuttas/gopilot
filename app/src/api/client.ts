import { isMockMode, moodleUrl } from '../config'
import { tokenStore } from '../lib/storage'
import { toFormBody, type Params } from './serialize'

/** Error codes after which the token is no longer usable. */
const LOGOUT_ERRORCODES = new Set(['invalidtoken', 'accessexception'])

/** Error returned by Moodle (`exception` object) or by the network layer. */
export class MoodleError extends Error {
  readonly errorcode: string
  readonly exception: string

  constructor(message: string, errorcode: string, exception = '') {
    super(message)
    this.name = 'MoodleError'
    this.errorcode = errorcode
    this.exception = exception
  }
}

type AuthFailureHandler = () => void
let authFailureHandler: AuthFailureHandler | null = null

/** Registers what happens when Moodle rejects the token (usually: logout). */
export function setAuthFailureHandler(handler: AuthFailureHandler | null): void {
  authFailureHandler = handler
}

interface MoodleException {
  exception: string
  errorcode: string
  message: string
}

function isException(data: unknown): data is MoodleException {
  return typeof data === 'object' && data !== null && 'exception' in data
}

async function post(path: string, body: URLSearchParams): Promise<unknown> {
  let response: Response
  try {
    // Simple CORS request: POST, form-urlencoded, no custom headers (ADR 0007).
    response = await fetch(`${moodleUrl}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
  } catch {
    throw new MoodleError(
      'Moodle ist gerade nicht erreichbar. Prüfe deine Internetverbindung.',
      'networkerror',
    )
  }
  if (!response.ok) {
    throw new MoodleError(`Moodle antwortet mit Fehler ${response.status}.`, 'httperror')
  }
  try {
    return await response.json()
  } catch {
    throw new MoodleError('Die Antwort von Moodle ist unlesbar.', 'invalidresponse')
  }
}

function handleResult<T>(data: unknown): T {
  if (isException(data)) {
    if (LOGOUT_ERRORCODES.has(data.errorcode)) {
      tokenStore.clear()
      authFailureHandler?.()
    }
    throw new MoodleError(data.message || 'Unbekannter Fehler', data.errorcode, data.exception)
  }
  return data as T
}

/** Calls a Moodle web service function of the `gopilot` service. */
export async function call<T>(wsfunction: string, params: Params = {}): Promise<T> {
  const token = tokenStore.get()
  if (!token) {
    authFailureHandler?.()
    throw new MoodleError('Du bist nicht angemeldet.', 'invalidtoken')
  }
  if (isMockMode()) {
    const { mockCall } = await import('./mock/server')
    return handleResult<T>(await mockCall(wsfunction, params, token))
  }
  const body = toFormBody({ wstoken: token, wsfunction, moodlewsrestformat: 'json', ...params })
  return handleResult<T>(await post('/webservice/rest/server.php', body))
}

const LOGIN_MESSAGES: Record<string, string> = {
  invalidlogin: 'Benutzername oder Kennwort ist falsch.',
  usernamenotfound: 'Benutzername oder Kennwort ist falsch.',
  enablewsdescription: 'Die Webservices sind in Moodle nicht aktiviert. Bitte wende dich an die Moodle-Administration.',
  servicenotavailable: 'Der Dienst „gopilot“ ist in Moodle nicht verfügbar. Bitte wende dich an die Moodle-Administration.',
  restoredaccountresetpassword: 'Bitte setze zuerst in Moodle ein neues Kennwort.',
  forcepasswordchangenotice: 'Bitte ändere zuerst in Moodle dein Kennwort.',
  sitemaintenance: 'Moodle wird gerade gewartet. Versuche es später noch einmal.',
}

/**
 * Requests a token via `login/token.php` (ADR 0006). The password is only
 * sent, never stored. Returns the token; the caller stores it.
 */
export async function login(username: string, password: string): Promise<string> {
  let data: unknown
  if (isMockMode()) {
    const { mockLogin } = await import('./mock/server')
    data = await mockLogin(username, password)
  } else {
    data = await post('/login/token.php', toFormBody({ username, password, service: 'gopilot' }))
  }
  const result = data as { token?: string; error?: string; errorcode?: string }
  if (result.token) return result.token
  const code = result.errorcode ?? 'loginfailed'
  throw new MoodleError(LOGIN_MESSAGES[code] ?? result.error ?? 'Anmeldung fehlgeschlagen.', code)
}

/** German, user-facing text for any error. */
export function errorMessage(error: unknown): string {
  if (error instanceof MoodleError) return error.message
  if (error instanceof Error) return error.message
  return 'Unbekannter Fehler'
}
