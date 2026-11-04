import { Button, Card, Group, List, Stack, Switch, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconDeviceMobile, IconLogout, IconShare2 } from '@tabler/icons-react'
import { useEffect, useState } from 'react'
import { errorMessage } from '../api/client'
import { useOverview } from '../api/hooks'
import { useAuth } from '../auth/useAuth'
import { Notice } from '../components/Notice'
import { PageHeader } from '../components/PageHeader'
import { isMockMode, moodleUrl } from '../config'
import { disablePush, enablePush, getPushSubscription, isIos, isPushSupported, isStandalone } from '../lib/push'

function PushSetting() {
  const supported = isPushSupported()
  const [enabled, setEnabled] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let alive = true
    getPushSubscription()
      .then((s) => alive && setEnabled(!!s))
      .catch(() => undefined)
    return () => {
      alive = false
    }
  }, [])

  const toggle = async (on: boolean) => {
    setBusy(true)
    try {
      if (on) await enablePush()
      else await disablePush()
      setEnabled(on)
      notifications.show({
        color: 'teal',
        message: on ? 'Benachrichtigungen sind eingeschaltet.' : 'Benachrichtigungen sind ausgeschaltet.',
      })
    } catch (e) {
      notifications.show({ color: 'red', title: 'Benachrichtigungen', message: errorMessage(e) })
    } finally {
      setBusy(false)
    }
  }

  return (
    <Stack gap="xs">
      <Switch
        label="Push-Benachrichtigungen auf diesem Gerät"
        description="z. B. bei neuem Coaching-Feedback oder wenn die Reflexion fällig ist"
        checked={enabled}
        disabled={!supported || busy}
        onChange={(e) => toggle(e.currentTarget.checked)}
      />
      {!supported && (
        <Text size="xs" c="dimmed">
          {isIos() && !isStandalone()
            ? 'Auf dem iPhone gehen Benachrichtigungen nur, wenn GoPilot zum Home-Bildschirm hinzugefügt ist.'
            : 'Dieser Browser unterstützt keine Push-Benachrichtigungen.'}
        </Text>
      )}
    </Stack>
  )
}

function InstallHint() {
  if (isStandalone()) {
    return <Text size="sm">GoPilot ist auf diesem Gerät installiert.</Text>
  }
  return (
    <Stack gap="xs">
      <Text size="sm">Installiere GoPilot wie eine App – dann startest du direkt vom Home-Bildschirm und bekommst Benachrichtigungen.</Text>
      {isIos() ? (
        <List size="sm" type="ordered">
          <List.Item>
            In Safari unten auf <IconShare2 size={14} aria-label="Teilen" style={{ verticalAlign: 'middle' }} /> „Teilen“ tippen.
          </List.Item>
          <List.Item>„Zum Home-Bildschirm“ wählen und bestätigen.</List.Item>
          <List.Item>GoPilot vom Home-Bildschirm öffnen und hier Benachrichtigungen einschalten.</List.Item>
        </List>
      ) : (
        <List size="sm" type="ordered">
          <List.Item>Im Browser-Menü (⋮) „App installieren“ bzw. „Zum Startbildschirm hinzufügen“ wählen.</List.Item>
          <List.Item>GoPilot anschließend vom Startbildschirm öffnen.</List.Item>
        </List>
      )}
    </Stack>
  )
}

export function SettingsPage() {
  const { logout } = useAuth()
  const overview = useOverview()
  const [busy, setBusy] = useState(false)

  return (
    <>
      <PageHeader title="Einstellungen" />
      <Stack gap="md">
        <Card withBorder radius="md" padding="md">
          <Stack gap="xs">
            <Title order={3} size="h4">
              Profil
            </Title>
            <Text size="sm">{overview.data?.fullname ?? '…'}</Text>
            <Text size="xs" c="dimmed">
              Angemeldet bei {isMockMode() ? 'Mock-Moodle (Entwicklung)' : moodleUrl}
            </Text>
            <Group>
              <Button
                color="red"
                variant="light"
                leftSection={<IconLogout size={16} />}
                loading={busy}
                onClick={async () => {
                  setBusy(true)
                  await logout()
                }}
              >
                Abmelden
              </Button>
            </Group>
            <Text size="xs" c="dimmed">
              Beim Abmelden wird der Zugangsschlüssel auf diesem Gerät gelöscht.
            </Text>
          </Stack>
        </Card>
        <Card withBorder radius="md" padding="md">
          <Stack gap="xs">
            <Title order={3} size="h4">
              Benachrichtigungen
            </Title>
            <PushSetting />
          </Stack>
        </Card>
        <Card withBorder radius="md" padding="md">
          <Stack gap="xs">
            <Group gap="xs">
              <IconDeviceMobile size={18} aria-hidden />
              <Title order={3} size="h4">
                App installieren
              </Title>
            </Group>
            <InstallHint />
          </Stack>
        </Card>
        {isMockMode() && (
          <Notice color="grape" title="Demo-Modus mit Beispieldaten">
            Alle Daten kommen aus Beispieldaten im Browser und gehen beim Neuladen verloren.
          </Notice>
        )}
      </Stack>
    </>
  )
}
