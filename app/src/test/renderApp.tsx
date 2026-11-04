import { render } from '@testing-library/react'
import { QueryClient } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router'
import { AppRoutes } from '../App'
import { AppProviders } from '../AppProviders'
import { resetMock } from '../api/mock/server'
import { tokenStore } from '../lib/storage'

/**
 * Renders the full app (mock backend) at `path`, optionally logged in as a
 * mock user id (11 Ada, 12 Ben, 13 Can, 2 Lena).
 */
export function renderApp(path: string, userid?: number, today?: Date) {
  resetMock(today)
  if (userid) tokenStore.set(`mock-${userid}`)
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AppProviders queryClient={queryClient}>
        <AppRoutes />
      </AppProviders>
    </MemoryRouter>,
  )
}
