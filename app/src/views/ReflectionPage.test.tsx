import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockRequests } from '../api/mock/server'
import { renderApp } from '../test/renderApp'

describe('Reflexion', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 11 - 1, 6, 15, 0)) // Freitag, KW 45
  })
  afterEach(() => vi.useRealTimers())

  it('zeigt die Sichtbarkeitshinweise', async () => {
    renderApp('/reflexion/2026-11-02', 12)
    expect(await screen.findByText('Sichtbar für alle deine Lehrkräfte')).toBeInTheDocument()
    expect(screen.getAllByText('Nur für die Lehrkräfte dieses Kurses sichtbar')).toHaveLength(2)
  })

  it('speichert allgemeine Reflexion, Kursreflexion und Selbsteinschätzung', async () => {
    renderApp('/reflexion/2026-11-02', 12)
    const general = await screen.findByLabelText(/Wie lief deine Woche insgesamt/)
    await userEvent.type(general, 'Besser als letzte Woche.')

    const lf5 = screen.getByRole('heading', { name: 'LF5 Software zur Verwaltung von Daten anpassen' }).closest('.mantine-Card-root') as HTMLElement
    await userEvent.type(within(lf5).getByLabelText(/Kursreflexion/), 'JOINs geübt.')
    const ls52 = within(lf5).getByRole('radiogroup', { name: /LS 5.2 SQL-Abfragen/ })
    await userEvent.click(within(ls52).getByRole('radio', { name: 'unsicher' }))
    const ls51 = within(lf5).getByRole('radiogroup', { name: /LS 5.1 Datenbank/ })
    await userEvent.click(within(ls51).getByRole('radio', { name: 'sicher' }))

    await userEvent.click(screen.getByRole('button', { name: 'Reflexion speichern' }))
    await waitFor(() =>
      expect(mockRequests.some((r) => r.wsfunction === 'local_gopilot_save_reflection')).toBe(true),
    )
    const req = mockRequests.find((r) => r.wsfunction === 'local_gopilot_save_reflection')!
    expect(req.params).toEqual({
      week: '2026-11-02',
      generalreflection: 'Besser als letzte Woche.',
      courses: [
        {
          courseid: 101,
          reflection: 'JOINs geübt.',
          selfassessments: [
            { sectionid: 1001, level: 1, comment: '' },
            { sectionid: 1002, level: 3, comment: '' },
          ],
        },
        { courseid: 102, reflection: '', selfassessments: [] },
      ],
    })
    expect(await screen.findByText('Reflexion gespeichert')).toBeInTheDocument()
  })

  it('zeigt den Feedback-Faden mit Antwortfeld', async () => {
    renderApp('/reflexion/2026-10-26', 12)
    expect(await screen.findByText(/Wo genau hakt es bei den JOINs/)).toBeInTheDocument()
    expect(screen.getAllByLabelText('Antwort an deine Lehrkraft').length).toBeGreaterThan(0)
  })
})
