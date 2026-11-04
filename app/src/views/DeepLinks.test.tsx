import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '../test/renderApp'

// Push and Moodle messages link to /woche/{week}, /lerndoku/{courseid}/{userid} and /.
describe('Deep-Links aus Benachrichtigungen', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 3, 10, 0))
  })
  afterEach(() => vi.useRealTimers())

  it('/woche/{week} öffnet Plan, Reflexion und Feedback-Faden der Woche', async () => {
    renderApp('/woche/2026-10-26', 12)
    expect(await screen.findByRole('heading', { name: 'Reflexion' })).toBeInTheDocument()
    expect(await screen.findByText(/Wo genau hakt es bei den JOINs/)).toBeInTheDocument()
    expect(screen.getByText('KW 44 · 26.10.–01.11.2026')).toBeInTheDocument()
  })

  it('/lerndoku/{courseid}/{userid} öffnet das Lernenden-Detail', async () => {
    renderApp('/lerndoku/101/12', 2)
    expect(await screen.findByRole('heading', { name: 'Ben Azubi' })).toBeInTheDocument()
  })

  it('behält den Deep-Link über die Anmeldung hinweg', async () => {
    renderApp('/lerndoku/101/12')
    await userEvent.type(screen.getByLabelText(/Moodle-Benutzername/), 'lena')
    await userEvent.type(screen.getByLabelText(/Moodle-Kennwort/), 'x')
    await userEvent.click(screen.getByRole('button', { name: 'Anmelden' }))
    expect(await screen.findByRole('heading', { name: 'Ben Azubi' })).toBeInTheDocument()
  })
})
