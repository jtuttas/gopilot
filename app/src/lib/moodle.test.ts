import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api/functions'
import { openInMoodle } from './moodle'

function fakeTab() {
  return { opener: {} as unknown, location: { href: '' } }
}

describe('openInMoodle', () => {
  afterEach(() => vi.restoreAllMocks())

  it('öffnet sofort einen Tab und lädt darin den Einmal-Link', async () => {
    const tab = fakeTab()
    const open = vi.spyOn(window, 'open').mockReturnValue(tab as unknown as Window)
    vi.spyOn(api, 'getMoodleLink').mockResolvedValue({
      url: 'https://moodle.example/local/gopilot/autologin.php?userid=5&key=abc',
      validuntil: 0,
    })

    await openInMoodle('https://moodle.example/mod/page/view.php?id=7')

    expect(open).toHaveBeenCalledWith('', '_blank')
    expect(api.getMoodleLink).toHaveBeenCalledWith('https://moodle.example/mod/page/view.php?id=7')
    expect(tab.location.href).toBe('https://moodle.example/local/gopilot/autologin.php?userid=5&key=abc')
    expect(tab.opener).toBeNull()
  })

  it('öffnet die Seite ohne automatische Anmeldung, wenn der Link nicht abrufbar ist', async () => {
    const tab = fakeTab()
    vi.spyOn(window, 'open').mockReturnValue(tab as unknown as Window)
    vi.spyOn(api, 'getMoodleLink').mockRejectedValue(new Error('autologinnotforadmins'))

    await openInMoodle('https://moodle.example/course/view.php?id=2')

    expect(tab.location.href).toBe('https://moodle.example/course/view.php?id=2')
  })
})
