import { Button, Group, Paper, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconSend } from '@tabler/icons-react'
import { useState } from 'react'
import { useAddFeedback } from '../api/hooks'
import { errorMessage } from '../api/client'
import type { FeedbackEntry } from '../api/types'
import { formatDateTime } from '../lib/week'

interface Props {
  entries: FeedbackEntry[]
  courseid: number
  /** The learner the thread belongs to. */
  learnerid: number
  week: string
  /** Who is looking: changes labels only. */
  viewer: 'learner' | 'coach'
  readOnly?: boolean
}

/** Coaching-Feedback thread per week and course, oldest first. */
export function FeedbackThread({ entries, courseid, learnerid, week, viewer, readOnly }: Props) {
  const [message, setMessage] = useState('')
  const add = useAddFeedback()
  const inputLabel = viewer === 'coach' ? 'Coaching-Feedback schreiben' : 'Antwort an deine Lehrkraft'

  const submit = () => {
    const text = message.trim()
    if (!text) return
    add.mutate(
      { courseid, userid: learnerid, week, message: text },
      {
        onSuccess: () => {
          setMessage('')
          notifications.show({ color: 'teal', message: 'Nachricht gesendet.' })
        },
        onError: (e) => notifications.show({ color: 'red', title: 'Nicht gesendet', message: errorMessage(e) }),
      },
    )
  }

  return (
    <Stack gap="xs">
      {entries.length === 0 ? (
        <Text size="sm" c="dimmed">
          {viewer === 'coach' ? 'Noch kein Coaching-Feedback zu dieser Woche.' : 'Noch kein Coaching-Feedback.'}
        </Text>
      ) : (
        entries.map((e) => (
          <Paper
            key={e.id}
            p="xs"
            radius="md"
            bg={e.isteacher ? 'var(--mantine-color-blue-light)' : 'var(--mantine-color-gray-light)'}
            ml={e.isteacher ? 0 : 'lg'}
            mr={e.isteacher ? 'lg' : 0}
          >
            <Group justify="space-between" gap="xs" wrap="wrap">
              <Text size="xs" fw={600}>
                {e.authorname}
                {e.isteacher ? ' (Lehrkraft)' : ''}
              </Text>
              <Text size="xs" c="dimmed">
                {formatDateTime(e.timecreated)}
              </Text>
            </Group>
            <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
              {e.message}
            </Text>
          </Paper>
        ))
      )}
      {!readOnly && (
        <Stack gap={6}>
          <Textarea
            label={inputLabel}
            placeholder={viewer === 'coach' ? 'Was ist dir aufgefallen? Was ist ein guter nächster Schritt?' : 'Deine Antwort …'}
            autosize
            minRows={2}
            value={message}
            onChange={(e) => setMessage(e.currentTarget.value)}
          />
          <Group justify="flex-end">
            <Button
              size="xs"
              leftSection={<IconSend size={14} />}
              onClick={submit}
              loading={add.isPending}
              disabled={!message.trim()}
            >
              Senden
            </Button>
          </Group>
        </Stack>
      )}
    </Stack>
  )
}
