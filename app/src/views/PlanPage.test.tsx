import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockRequests } from '../api/mock/server'
import { renderApp } from '../test/renderApp'

describe('Wochenplan', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 3, 10, 0))
  })
  afterEach(() => vi.useRealTimers())

  it('speichert Wochenziel und ausgewählte Lernschritte', async () => {
    renderApp('/plan/2026-11-02', 11)
    const goal = await screen.findByLabelText(/Wochenziel/)
    expect(goal).toHaveValue('SQL-Grundlagen sicher anwenden.')

    // Completed steps are hidden by default, except planned ones.
    expect(screen.queryByRole('checkbox', { name: /Wissenscheck ER-Modell/ })).toBeNull()
    expect(screen.getByRole('checkbox', { name: /Informationen: SELECT-Grundlagen/ })).toBeChecked()

    await userEvent.click(screen.getByRole('checkbox', { name: /Informationen: JOINs/ }))
    await userEvent.click(screen.getByRole('checkbox', { name: /SQL-Quiz/ })) // abwählen
    await userEvent.clear(goal)
    await userEvent.type(goal, '  JOINs verstehen  ')
    await userEvent.click(screen.getByRole('button', { name: 'Plan speichern' }))

    await waitFor(() => expect(mockRequests.some((r) => r.wsfunction === 'local_gopilot_save_plan')).toBe(true))
    const req = mockRequests.find((r) => r.wsfunction === 'local_gopilot_save_plan')!
    expect(req.params).toEqual({
      week: '2026-11-02',
      goal: 'JOINs verstehen',
      cmids: [5011, 5013, 8003],
    })
    expect(await screen.findByText('Dein Wochenplan ist gespeichert.')).toBeInTheDocument()
  })

  it('bietet diese und nächste Woche an', async () => {
    renderApp('/plan', 11)
    expect(await screen.findByText('Diese Woche')).toBeInTheDocument()
    expect(screen.getByText('Nächste Woche')).toBeInTheDocument()
  })
})
