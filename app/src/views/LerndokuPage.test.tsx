import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '../test/renderApp'

describe('Lerndoku', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 3, 10, 0)) // Dienstag, KW 45
  })
  afterEach(() => vi.useRealTimers())

  const row = (name: string) => screen.getByRole('link', { name }).closest('tr') as HTMLElement

  it('zeigt Wochenziel und Plan der laufenden Woche', async () => {
    renderApp('/lerndoku/101', 2)
    await screen.findByRole('link', { name: 'Ada Azubi' })
    expect(screen.getByText(/haben Lernschritte aus diesem Kurs geplant/)).toHaveTextContent(
      'KW 45: 2 von 3 haben Lernschritte aus diesem Kurs geplant, 2 von 3 haben ein Wochenziel.',
    )
    expect(within(row('Ada Azubi')).getByText('SQL-Grundlagen sicher anwenden.')).toBeInTheDocument()
    expect(within(row('Ada Azubi')).getByText(/geplant · \d+ erledigt/)).toBeInTheDocument()
    expect(within(row('Can Azubi')).getByText('kein Wochenplan')).toBeInTheDocument()
    expect(within(row('Can Azubi')).getByText('kein Wochenziel')).toBeInTheDocument()
  })

  it('filtert nach Lernenden ohne Plan', async () => {
    const user = userEvent.setup()
    renderApp('/lerndoku/101', 2)
    await screen.findByRole('link', { name: 'Ada Azubi' })
    await user.click(screen.getByRole('textbox', { name: 'Diese Woche' }))
    await user.click(await screen.findByRole('option', { name: 'Ohne Plan in diesem Kurs' }))
    expect(screen.queryByRole('link', { name: 'Ada Azubi' })).toBeNull()
    expect(screen.getByRole('link', { name: 'Can Azubi' })).toBeInTheDocument()
  })
})
