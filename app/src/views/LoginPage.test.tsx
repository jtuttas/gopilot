import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '../test/renderApp'
import { tokenStore } from '../lib/storage'

describe('Anmeldung', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 3, 10, 0))
  })
  afterEach(() => vi.useRealTimers())

  it('hat beschriftete Felder und den SSO-Hinweis', () => {
    renderApp('/')
    expect(screen.getByLabelText(/Moodle-Benutzername/)).toBeInTheDocument()
    expect(screen.getByLabelText(/Moodle-Kennwort/)).toBeInTheDocument()
    expect(screen.getByText(/Single Sign-on/)).toBeInTheDocument()
  })

  it('meldet fehlende Eingaben', async () => {
    renderApp('/')
    await userEvent.click(screen.getByRole('button', { name: 'Anmelden' }))
    expect(screen.getByRole('alert')).toHaveTextContent('Bitte gib Benutzername und Kennwort ein.')
  })

  it('zeigt eine klare Meldung bei falschem Kennwort', async () => {
    renderApp('/')
    await userEvent.type(screen.getByLabelText(/Moodle-Benutzername/), 'ada')
    await userEvent.type(screen.getByLabelText(/Moodle-Kennwort/), 'falsch')
    await userEvent.click(screen.getByRole('button', { name: 'Anmelden' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Benutzername oder Kennwort ist falsch.')
    expect(tokenStore.get()).toBeNull()
  })

  it('speichert nach erfolgreicher Anmeldung nur das Token und zeigt die Startseite', async () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem')
    renderApp('/')
    await userEvent.type(screen.getByLabelText(/Moodle-Benutzername/), 'ada')
    await userEvent.type(screen.getByLabelText(/Moodle-Kennwort/), 'Test-Kennwort-1')
    await userEvent.click(screen.getByRole('button', { name: 'Anmelden' }))
    expect(await screen.findByRole('heading', { name: 'Hallo Ada!' })).toBeInTheDocument()
    await waitFor(() => expect(tokenStore.get()).toBe('mock-11'))
    for (const [, value] of setItem.mock.calls) expect(value).not.toContain('Test-Kennwort-1')
    setItem.mockRestore()
  })
})
