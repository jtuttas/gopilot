import { Alert, Button, Center, Loader, Stack, Text } from '@mantine/core'
import { IconAlertCircle } from '@tabler/icons-react'
import { errorMessage } from '../api/client'

export function Loading({ label = 'Wird geladen …' }: { label?: string }) {
  return (
    <Center py="xl" role="status" aria-live="polite">
      <Stack align="center" gap="xs">
        <Loader size="sm" />
        <Text size="sm" c="dimmed">
          {label}
        </Text>
      </Stack>
    </Center>
  )
}

export function ErrorAlert({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  return (
    <Alert color="red" icon={<IconAlertCircle />} title="Das hat nicht geklappt" role="alert">
      <Stack gap="xs" align="flex-start">
        <Text size="sm">{errorMessage(error)}</Text>
        {onRetry && (
          <Button size="xs" variant="light" color="red" onClick={onRetry}>
            Erneut versuchen
          </Button>
        )}
      </Stack>
    </Alert>
  )
}
