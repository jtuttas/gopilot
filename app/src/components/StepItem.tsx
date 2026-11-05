import type { MouseEvent } from 'react'
import { ActionIcon, Badge, Checkbox, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import {
  IconCircle,
  IconCircleCheckFilled,
  IconClock,
  IconExternalLink,
} from '@tabler/icons-react'
import { useStepCompletion } from '../api/hooks'
import { errorMessage } from '../api/client'
import type { Meta, Step } from '../api/types'
import { LEVEL_LABELS } from '../lib/ampel'
import { formatDate } from '../lib/week'
import { formatDuration, modLabel } from '../lib/format'
import { openInMoodle } from '../lib/moodle'

export function MetaBadges({ meta }: { meta: Meta }) {
  const parts: string[] = []
  if (meta.phase) parts.push(meta.phase)
  if (meta.duration) parts.push(formatDuration(meta.duration))
  if (meta.level) parts.push(`Niveau ${LEVEL_LABELS[meta.level] ?? meta.level}`)
  if (parts.length === 0) return null
  return (
    <Group gap={4}>
      {parts.map((p) => (
        <Badge key={p} size="xs" variant="default" styles={{ label: { textTransform: 'none' } }}>
          {p}
        </Badge>
      ))}
    </Group>
  )
}

interface StepItemProps {
  step: Step
  /** Allow ticking manual steps (learner, open week). */
  canTick?: boolean
  showMeta?: boolean
}

/** One Lernschritt with status, Soll-Termin, metadata and Moodle link. */
export function StepItem({ step, canTick = true, showMeta = true }: StepItemProps) {
  const completion = useStepCompletion()
  const tickable = canTick && step.manual
  const pending = completion.isPending

  const toggle = (checked: boolean) =>
    completion.mutate(
      { cmid: step.cmid, completed: checked },
      {
        onError: (e) =>
          notifications.show({ color: 'red', title: 'Nicht gespeichert', message: errorMessage(e) }),
      },
    )

  const statusText = step.completed ? 'erledigt' : step.tracked ? 'offen' : 'ohne Abschluss'

  return (
    <Group wrap="nowrap" align="flex-start" gap="sm" py={6}>
      {tickable ? (
        <Checkbox
          mt={2}
          checked={step.completed}
          disabled={pending}
          onChange={(e) => toggle(e.currentTarget.checked)}
          aria-label={`${step.name} als erledigt markieren`}
        />
      ) : (
        <Tooltip
          label={
            step.completed
              ? 'Erledigt'
              : step.manual
                ? 'Offen'
                : 'Wird in Moodle automatisch abgeschlossen'
          }
        >
          <ThemeIcon variant="transparent" color={step.completed ? 'teal' : 'gray'} size={20} mt={1}>
            {step.completed ? <IconCircleCheckFilled size={20} /> : <IconCircle size={20} />}
          </ThemeIcon>
        </Tooltip>
      )}
      <Stack gap={2} style={{ flex: 1, minWidth: 0 }}>
        <Text size="sm" fw={500} c={step.completed ? 'dimmed' : undefined}>
          <Text span inherit td={step.completed ? 'line-through' : undefined}>
            {step.name}
          </Text>
          <Text span size="xs" c="dimmed" fw={400}>
            {' '}
            · {modLabel(step.modname)} · {statusText}
          </Text>
        </Text>
        <Group gap={6}>
          {step.expected > 0 && (
            <Badge
              size="xs"
              variant={step.overdue ? 'light' : 'transparent'}
              color={step.overdue ? 'orange' : 'gray'}
              leftSection={<IconClock size={11} aria-hidden />}
              styles={{ label: { textTransform: 'none' } }}
              px={step.overdue ? undefined : 0}
            >
              {step.overdue ? `Soll-Termin war ${formatDate(step.expected)}` : `Soll-Termin ${formatDate(step.expected)}`}
            </Badge>
          )}
          {showMeta && <MetaBadges meta={step.meta} />}
        </Group>
      </Stack>
      <Tooltip label="In Moodle öffnen">
        <ActionIcon
          component="a"
          href={step.url}
          target="_blank"
          rel="noopener noreferrer"
          onClick={(e: MouseEvent) => {
            // Plain clicks log in automatically; modifier clicks keep the browser's own behaviour.
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return
            e.preventDefault()
            void openInMoodle(step.url)
          }}
          variant="subtle"
          aria-label={`${step.name} in Moodle öffnen (neuer Tab)`}
        >
          <IconExternalLink size={18} />
        </ActionIcon>
      </Tooltip>
    </Group>
  )
}
