import {
  Alert,
  Anchor,
  Button,
  Center,
  Code,
  Paper,
  PasswordInput,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { IconAlertCircle, IconInfoCircle } from '@tabler/icons-react'
import { useState, type FormEvent } from 'react'
import { errorMessage } from '../api/client'
import { useAuth } from '../auth/useAuth'
import { Logo } from '../components/Logo'
import { Notice } from '../components/Notice'
import { isMockMode, moodleUrl } from '../config'

export function LoginPage() {
  const { login } = useAuth()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (!username.trim() || !password) {
      setError('Bitte gib Benutzername und Kennwort ein.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      await login(username.trim(), password)
    } catch (err) {
      setError(errorMessage(err))
      setPassword('')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Center mih="100dvh" p="md" bg="var(--mantine-color-gray-0)">
      <Paper withBorder shadow="sm" radius="lg" p="xl" w="100%" maw={420}>
        <Stack gap="md">
          <Stack align="center" gap={6}>
            <Logo size={64} />
            <Title order={1} size="h2">
              GoPilot
            </Title>
            <Text size="sm" c="dimmed" ta="center">
              Selbstgesteuert lernen mit Moodle
            </Text>
          </Stack>

          <form onSubmit={submit} noValidate>
            <Stack gap="sm">
              <TextInput
                label="Moodle-Benutzername"
                autoComplete="username"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                value={username}
                onChange={(e) => setUsername(e.currentTarget.value)}
                required
              />
              <PasswordInput
                label="Moodle-Kennwort"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.currentTarget.value)}
                required
              />
              {error && (
                <Alert color="red" icon={<IconAlertCircle />} role="alert" p="xs">
                  {error}
                </Alert>
              )}
              <Button type="submit" loading={busy} fullWidth>
                Anmelden
              </Button>
            </Stack>
          </form>

          <Text size="xs" c="dimmed">
            Melde dich mit denselben Zugangsdaten an wie in Moodle. Konten, die sich nur über
            Single Sign-on (z. B. Microsoft oder IServ) anmelden, funktionieren in GoPilot nicht.
            Dein Kennwort wird nicht gespeichert.
          </Text>
          {moodleUrl && !isMockMode() && (
            <Text size="xs" c="dimmed">
              Moodle:{' '}
              <Anchor href={moodleUrl} target="_blank" rel="noopener noreferrer" size="xs">
                {moodleUrl}
              </Anchor>
            </Text>
          )}
          {isMockMode() && (
            <Notice color="grape" icon={<IconInfoCircle size={18} />} p="xs" title="Demo-Modus mit Beispieldaten">
              <Text size="xs">
                Benutzer <Code>ada</Code>, <Code>ben</Code>, <Code>can</Code> (Lernende) oder <Code>lena</Code>{' '}
                (Lehrkraft), beliebiges Kennwort außer <Code>falsch</Code>.
              </Text>
            </Notice>
          )}
        </Stack>
      </Paper>
    </Center>
  )
}
