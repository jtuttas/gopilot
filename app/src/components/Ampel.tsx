import { Badge, Box, Group, List, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core'
import {
  IconAlertCircle,
  IconAlertTriangle,
  IconCircleCheck,
  IconCircleDashed,
} from '@tabler/icons-react'
import type { Ampel, AmpelColour, Signal } from '../api/types'
import {
  ampelDisplay,
  signalColour,
  signalLabel,
  SIGNAL_SHORT,
} from '../lib/ampel'

export function AmpelIcon({ colour, size = 18 }: { colour: AmpelColour; size?: number }) {
  const props = { size, 'aria-hidden': true as const }
  switch (colour) {
    case 'green':
      return <IconCircleCheck {...props} />
    case 'yellow':
      return <IconAlertTriangle {...props} />
    case 'red':
      return <IconAlertCircle {...props} />
    default:
      return <IconCircleDashed {...props} />
  }
}

/** Colour + icon + word, so colour is never the only cue. */
export function AmpelBadge({ colour, size = 'md' }: { colour: AmpelColour; size?: 'sm' | 'md' | 'lg' }) {
  const d = ampelDisplay(colour)
  return (
    <Badge
      size={size}
      color={d.color}
      variant={colour === 'none' ? 'outline' : 'light'}
      c={colour === 'yellow' ? 'yellow.9' : undefined}
      leftSection={<AmpelIcon colour={colour} size={14} />}
      styles={{ label: { textTransform: 'none' } }}
    >
      {colour === 'none' ? d.label : `Ampel ${d.label}`}
    </Badge>
  )
}

/** Compact signal badge with the reason as tooltip (Lerndoku). */
export function SignalBadge({ signal }: { signal: Signal }) {
  const colour = signalColour(signal)
  const d = ampelDisplay(colour)
  const label = SIGNAL_SHORT[signal.key] ?? signal.key
  const text = `${signalLabel(signal.key)}: ${signal.active ? d.label : 'nicht aktiv'} – ${signal.reason}`
  return (
    <Tooltip label={text} multiline w={260} withArrow events={{ hover: true, focus: true, touch: true }}>
      <Badge
        tabIndex={0}
        aria-label={text}
        size="sm"
        color={d.color}
        variant={signal.active ? 'light' : 'outline'}
        c={colour === 'yellow' ? 'yellow.9' : undefined}
        leftSection={<AmpelIcon colour={colour} size={12} />}
        styles={{ label: { textTransform: 'none' } }}
      >
        {label}
      </Badge>
    </Tooltip>
  )
}

/** Full signal list with reasons. */
export function SignalList({ signals, onlyActive = false }: { signals: Signal[]; onlyActive?: boolean }) {
  const shown = onlyActive ? signals.filter((s) => s.active) : signals
  if (shown.length === 0) {
    return (
      <Text size="sm" c="dimmed">
        Noch keine auswertbaren Signale.
      </Text>
    )
  }
  return (
    <List spacing={6} size="sm" center>
      {shown.map((s) => {
        const colour = signalColour(s)
        const d = ampelDisplay(colour)
        return (
          <List.Item
            key={s.key}
            icon={
              <ThemeIcon color={d.color} variant="light" size={22} radius="xl">
                <AmpelIcon colour={colour} size={14} />
              </ThemeIcon>
            }
          >
            <Text span size="sm" fw={600}>
              {signalLabel(s.key)}
            </Text>
            <Text span size="sm" c="dimmed">
              {' '}
              ({s.active ? d.label : 'nicht aktiv'})
            </Text>
            <Text size="sm">{s.reason}</Text>
          </List.Item>
        )
      })}
    </List>
  )
}

/** Ampel header block used in cards and the learner detail. */
export function AmpelSummary({ ampel, audience }: { ampel: Ampel; audience: 'learner' | 'coach' }) {
  const d = ampelDisplay(ampel.colour)
  return (
    <Stack gap="xs">
      <Group gap="xs" wrap="wrap">
        <AmpelBadge colour={ampel.colour} />
        <Text size="xs" c="dimmed">
          Hinweis, keine Bewertung
        </Text>
      </Group>
      <Text size="sm">{audience === 'learner' ? d.learnerHint : d.coachHint}</Text>
      <Box>
        <SignalList signals={ampel.signals} onlyActive={audience === 'learner'} />
      </Box>
    </Stack>
  )
}
