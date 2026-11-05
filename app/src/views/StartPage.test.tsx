import { screen, within } from '@testing-library/react'
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
