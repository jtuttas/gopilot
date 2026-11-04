import { useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { notifications } from '@mantine/notifications'
import { login as requestToken, setAuthFailureHandler } from '../api/client'
import { tokenStore } from '../lib/storage'
import { disablePush } from '../lib/push'

export interface AuthState {
  token: string | null
  login: (username: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

// eslint-disable-next-line react-refresh/only-export-components
export const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setToken] = useState<string | null>(() => tokenStore.get())

  const discardSession = useCallback(() => {
    tokenStore.clear()
    queryClient.clear()
    setToken(null)
  }, [queryClient])

  useEffect(() => {
    setAuthFailureHandler(() => {
      if (token === null) return
      discardSession()
      notifications.show({
        color: 'orange',
        title: 'Abgemeldet',
        message: 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.',
      })
    })
    return () => setAuthFailureHandler(null)
  }, [discardSession, token])

  const login = useCallback(async (username: string, password: string) => {
    const newToken = await requestToken(username, password)
    tokenStore.set(newToken)
    setToken(newToken)
  }, [])

  const logout = useCallback(async () => {
    // Stop pushes for this person on this device, then drop the token (ADR 0006).
    try {
      await disablePush()
    } catch {
      // best effort
    }
    discardSession()
  }, [discardSession])

  const value = useMemo(() => ({ token, login, logout }), [token, login, logout])
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
