import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '../test/renderApp'

describe('Startseite', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 3, 10, 0)) // Dienstag, KW 45
  })
  afterEach(() => vi.useRealTimers())

  it('zeigt Woche, Wochenziel und geplante Lernschritte', async () => {
    renderApp('/', 11)
    expect(await screen.findByRole('heading', { name: 'Hallo Ada!' })).toBeInTheDocument()
    expect(screen.getByText('KW 45 · 02.11.–08.11.2026')).toBeInTheDocument()
    expect(screen.getByText('SQL-Grundlagen sicher anwenden.')).toBeInTheDocument()
    // Manual step can be ticked, automatically completed ones not.
    expect(screen.getByRole('checkbox', { name: /Informationen: SELECT-Grundlagen als erledigt markieren/ })).toBeChecked()
    expect(screen.queryByRole('checkbox', { name: /SQL-Quiz/ })).toBeNull()
  })

  it('zeigt die Ampel pro Kurs mit Farbe als Text und Begründungen', async () => {
    renderApp('/', 11)
    const lf5 = (await screen.findByRole('heading', { name: 'LF5 Software zur Verwaltung von Daten anpassen' })).closest('.mantine-Card-root') as HTMLElement
    expect(within(lf5).getByText('Ampel Grün')).toBeInTheDocument()
    expect(within(lf5).getAllByText('Hinweis, keine Bewertung').length).toBeGreaterThan(0)
    expect(within(lf5).getByText('2 von 2 geplanten Lernschritten erledigt (100 %)')).toBeInTheDocument()
    expect(within(lf5).getByText(/fälligen Lernschritte erledigt$/)).toBeInTheDocument()
  })

  it('zeigt neues Coaching-Feedback ganz oben, bis es gelesen ist', async () => {
    const user = userEvent.setup()
    renderApp('/', 12)
    const card = (await screen.findByRole('heading', { name: 'Neue Nachricht von deiner Lehrkraft' })).closest(
      '.mantine-Card-root',
    ) as HTMLElement
    expect(within(card).getByText(/Wo genau hakt es bei den JOINs/)).toBeInTheDocument()
    expect(within(card).getByText(/Lena .* · LF5 .* · KW 44/)).toBeInTheDocument()
    expect(within(card).getByRole('link', { name: 'Antworten' })).toHaveAttribute('href', '/reflexion/2026-10-26')
    await user.click(within(card).getByRole('button', { name: 'Gelesen' }))
    await waitFor(() => expect(screen.queryByRole('heading', { name: /Neue Nachricht/ })).toBeNull())
  })

  it('zeigt gelesenes Feedback nicht mehr auf der Startseite', async () => {
    renderApp('/', 11)
    await screen.findByRole('heading', { name: 'Hallo Ada!' })
    expect(screen.queryByRole('heading', { name: /Neue Nachricht/ })).toBeNull()
  })

  it('zeigt Gelb und Rot unterstützend formuliert', async () => {
    renderApp('/', 12)
    expect(await screen.findAllByText('Ampel Gelb')).not.toHaveLength(0)
    expect(screen.getByText(/Selbsteinschätzung „unsicher“ in LS 5.2/)).toBeInTheDocument()
  })

  it('fordert Can zum Planen und zum Nachholen der Reflexion auf', async () => {
    renderApp('/', 13)
    expect(await screen.findAllByText('Ampel Rot')).not.toHaveLength(0)
    expect(screen.getByRole('link', { name: 'Woche planen' })).toHaveAttribute('href', '/plan/2026-11-02')
    expect(await screen.findByRole('link', { name: 'Letzte Woche reflektieren' })).toHaveAttribute(
      'href',
      '/reflexion/2026-10-26',
    )
    expect(screen.getByText(/^4 von \d+ fälligen Lernschritten noch offen$/)).toBeInTheDocument()
  })

  it('zeigt Lehrkräften ihre Kurse mit Link zur Lerndoku', async () => {
    renderApp('/', 2)
    expect(await screen.findByRole('heading', { name: 'Hallo Lena!' })).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: /Lerndoku/ }).length).toBeGreaterThan(0)
    expect(screen.queryByText('Ampel Grün')).toBeNull()
  })
})
