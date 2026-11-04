import { MantineProvider } from '@mantine/core'
import { DatesProvider } from '@mantine/dates'
import { Notifications } from '@mantine/notifications'
import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import dayjs from 'dayjs'
import 'dayjs/locale/de'
import { useState, type ReactNode } from 'react'
import { createQueryClient } from './lib/queryClient'
import { theme } from './theme'
import { AuthProvider } from './auth/AuthContext'

dayjs.locale('de')

export function AppProviders({ children, queryClient }: { children: ReactNode; queryClient?: QueryClient }) {
  const [client] = useState(() => queryClient ?? createQueryClient())
  return (
    <MantineProvider theme={theme} defaultColorScheme="light">
      <DatesProvider settings={{ locale: 'de', firstDayOfWeek: 1 }}>
        <Notifications position="top-center" />
        <QueryClientProvider client={client}>
          <AuthProvider>{children}</AuthProvider>
        </QueryClientProvider>
      </DatesProvider>
    </MantineProvider>
  )
}
