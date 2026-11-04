import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { call, login, MoodleError, setAuthFailureHandler } from './client'
import { api } from './functions'
import { tokenStore } from '../lib/storage'

function jsonResponse(data: unknown) {
  return new Response(JSON.stringify(data), { status: 200, headers: { 'Content-Type': 'application/json' } })
}

describe('API-Client (echtes Moodle)', () => {
  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    vi.stubEnv('VITE_MOCK', '0')
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    tokenStore.set('abc123')
  })

  afterEach(() => {
    vi.unstubAllEnvs()
    vi.unstubAllGlobals()
    setAuthFailureHandler(null)
  })

  it('sendet POST form-urlencoded mit wstoken als Formularfeld', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ success: true }))
    await api.savePlan('2026-11-02', 'Ziel', [5, 7])
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/webservice\/rest\/server\.php$/)
    expect(init.method).toBe('POST')
    expect(init.headers).toEqual({ 'Content-Type': 'application/x-www-form-urlencoded' })
    expect(init.credentials).toBeUndefined()
    const body = new URLSearchParams(init.body as string)
    expect(body.get('wstoken')).toBe('abc123')
    expect(body.get('wsfunction')).toBe('local_gopilot_save_plan')
    expect(body.get('moodlewsrestformat')).toBe('json')
    expect(body.get('week')).toBe('2026-11-02')
    expect(body.get('cmids[0]')).toBe('5')
    expect(body.get('cmids[1]')).toBe('7')
  })

  it('wirft MoodleError bei exception und bleibt angemeldet', async () => {
    const onFail = vi.fn()
    setAuthFailureHandler(onFail)
    fetchMock.mockResolvedValue(
      jsonResponse({ exception: 'moodle_exception', errorcode: 'weekclosed', message: 'Woche abgeschlossen' }),
    )
    const err = (await call('local_gopilot_save_plan').catch((e: unknown) => e)) as MoodleError
    expect(err).toBeInstanceOf(MoodleError)
    expect(err.message).toBe('Woche abgeschlossen')
    expect(err.errorcode).toBe('weekclosed')
    expect(onFail).not.toHaveBeenCalled()
    expect(tokenStore.get()).toBe('abc123')
  })

  it.each(['invalidtoken', 'accessexception'])('meldet bei %s ab', async (code) => {
    const onFail = vi.fn()
    setAuthFailureHandler(onFail)
    fetchMock.mockResolvedValue(jsonResponse({ exception: 'x', errorcode: code, message: 'Ungültig' }))
    await expect(call('local_gopilot_get_overview')).rejects.toBeInstanceOf(MoodleError)
    expect(onFail).toHaveBeenCalledTimes(1)
    expect(tokenStore.get()).toBeNull()
  })

  it('übersetzt Netzwerkfehler in eine verständliche Meldung', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    await expect(call('local_gopilot_get_overview')).rejects.toMatchObject({ errorcode: 'networkerror' })
  })

  it('ruft ohne Token nicht auf, sondern meldet ab', async () => {
    tokenStore.clear()
    const onFail = vi.fn()
    setAuthFailureHandler(onFail)
    await expect(call('local_gopilot_get_overview')).rejects.toMatchObject({ errorcode: 'invalidtoken' })
    expect(fetchMock).not.toHaveBeenCalled()
    expect(onFail).toHaveBeenCalled()
  })

  it('login holt ein Token über login/token.php mit service=gopilot', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ token: 'tok', privatetoken: null }))
    await expect(login('ada', 'Test-Kennwort-1')).resolves.toBe('tok')
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(url).toMatch(/\/login\/token\.php$/)
    const body = new URLSearchParams(init.body as string)
    expect(body.get('service')).toBe('gopilot')
    expect(body.get('username')).toBe('ada')
  })

  it('login übersetzt invalidlogin ins Deutsche', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ error: 'Invalid login', errorcode: 'invalidlogin' }))
    await expect(login('ada', 'x')).rejects.toThrow('Benutzername oder Kennwort ist falsch.')
  })
})
