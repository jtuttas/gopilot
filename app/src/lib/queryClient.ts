import { QueryClient } from '@tanstack/react-query'
import { MoodleError } from '../api/client'

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        // Moodle errors (permissions, validation) do not get better by retrying.
        retry: (count, error) =>
          count < 2 && (!(error instanceof MoodleError) || error.errorcode === 'networkerror'),
      },
      mutations: { retry: false },
    },
  })
}
