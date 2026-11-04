import {
  Anchor,
  Badge,
  Button,
  Card,
  Divider,
  Group,
  List,
  Stack,
  Text,
  ThemeIcon,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconCircle, IconCircleCheckFilled, IconFileDownload } from '@tabler/icons-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import { useBiography, useOverview } from '../api/hooks'
import type { Week } from '../api/types'
import { FeedbackThread } from '../components/FeedbackThread'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { selfAssessmentLabel } from '../lib/ampel'
import { weekTitle } from '../lib/week'

async function exportPdf(fullname: string, weeks: Week[], filename: string) {
  const { downloadBiographyPdf } = await import('../pdf/biographyPdf')
  await downloadBiographyPdf({ fullname, weeks, filename })
}

function BiographyWeek({ week, userid, onExport, exporting }: { week: Week; userid: number; onExport: () => void; exporting: boolean }) {
  const done = week.items.filter((s) => s.completed).length
  return (
    <Card withBorder radius="md" padding="md">
      <Stack gap="sm">
        <Group justify="space-between" wrap="wrap" gap="xs">
          <Title order={3} size="h4">
            {weekTitle(week.week)}
          </Title>
          <Group gap="xs">
            {!week.closed && <Badge variant="light">laufend</Badge>}
            <Button
              size="compact-sm"
              variant="subtle"
              leftSection={<IconFileDownload size={14} />}
              onClick={onExport}
              loading={exporting}
              aria-label={`${weekTitle(week.week)} als PDF exportieren`}
            >
              PDF
            </Button>
          </Group>
        </Group>

        <div>
          <Text size="xs" c="dimmed">
            Wochenziel
          </Text>
          <Text size="sm">{week.goal || '–'}</Text>
        </div>

        {week.items.length > 0 && (
          <div>
            <Text size="xs" c="dimmed" mb={4}>
              Wochenplan: {done} von {week.items.length} erledigt{week.closed ? ' (Stand Wochenende)' : ''}
            </Text>
            <List spacing={2} size="sm" center>
              {week.items.map((s) => (
                <List.Item
                  key={s.cmid}
                  icon={
                    <ThemeIcon size={18} variant="transparent" color={s.completed ? 'teal' : 'gray'}>
                      {s.completed ? <IconCircleCheckFilled size={16} /> : <IconCircle size={16} />}
                    </ThemeIcon>
                  }
                >
                  {s.name}
                  <Text span size="xs" c="dimmed">
                    {s.completed ? ' – erledigt' : ' – offen'}
                  </Text>
                </List.Item>
              ))}
            </List>
          </div>
        )}

        {week.generalreflection && (
          <div>
            <Text size="xs" c="dimmed">
              Allgemeine Reflexion
            </Text>
            <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
              {week.generalreflection}
            </Text>
          </div>
        )}

        {week.courses
          .filter((c) => c.reflection || c.selfassessments.some((s) => s.level > 0) || c.feedback.length > 0)
          .map((c) => (
            <Stack key={c.courseid} gap={6}>
              <Divider label={c.fullname} labelPosition="left" />
              {c.reflection && (
                <div>
                  <Text size="xs" c="dimmed">
                    Kursreflexion
                  </Text>
                  <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                    {c.reflection}
                  </Text>
                </div>
              )}
              {c.selfassessments
                .filter((s) => s.level > 0)
                .map((s) => (
                  <Text key={s.sectionid} size="sm">
                    <Text span size="xs" c="dimmed">
                      Selbsteinschätzung {s.name}:{' '}
                    </Text>
                    {selfAssessmentLabel(s.level)}
                    {s.comment && ` – ${s.comment}`}
                  </Text>
                ))}
              {c.feedback.length > 0 && (
                <FeedbackThread entries={c.feedback} courseid={c.courseid} learnerid={userid} week={week.week} viewer="learner" readOnly />
              )}
            </Stack>
          ))}
        <Anchor component={Link} to={`/reflexion/${week.week}`} size="sm">
          Zur Reflexion dieser Woche
        </Anchor>
      </Stack>
    </Card>
  )
}

export function BiographyPage() {
  const overview = useOverview()
  const bio = useBiography()
  const [exporting, setExporting] = useState<string | null>(null)

  if (bio.isPending || overview.isPending) return <Loading />
  if (bio.isError) return <ErrorAlert error={bio.error} onRetry={() => bio.refetch()} />
  if (overview.isError) return <ErrorAlert error={overview.error} />

  const weeks = bio.data.pages.flatMap((p) => p.weeks)
  const total = bio.data.pages[0]?.total ?? 0
  const fullname = overview.data.fullname

  const run = async (key: string, list: Week[], filename: string) => {
    setExporting(key)
    try {
      await exportPdf(fullname, list, filename)
    } catch (e) {
      notifications.show({ color: 'red', title: 'PDF-Export fehlgeschlagen', message: errorMessage(e) })
    } finally {
      setExporting(null)
    }
  }

  return (
    <>
      <PageHeader
        title="Lernbiografie"
        description="Deine Wochen mit Plan, Ziel, Reflexion und Feedback. Den PDF-Export startest nur du – du entscheidest, wem du ihn gibst."
        actions={
          weeks.length > 0 && (
            <Button
              leftSection={<IconFileDownload size={16} />}
              variant="light"
              loading={exporting === 'all'}
              onClick={() => run('all', weeks, 'lernbiografie.pdf')}
            >
              PDF ({weeks.length} {weeks.length === 1 ? 'Woche' : 'Wochen'})
            </Button>
          )
        }
      />
      {weeks.length === 0 ? (
        <Text c="dimmed">Noch keine Wochen. Sobald du planst oder reflektierst, erscheint hier deine Lernbiografie.</Text>
      ) : (
        <Stack gap="md">
          {weeks.map((w) => (
            <BiographyWeek
              key={w.week}
              week={w}
              userid={overview.data.userid}
              exporting={exporting === w.week}
              onExport={() => run(w.week, [w], `lernbiografie-${w.week}.pdf`)}
            />
          ))}
          <Text size="xs" c="dimmed" ta="center">
            {weeks.length} von {total} Wochen geladen
          </Text>
          {bio.hasNextPage && (
            <Button variant="default" onClick={() => bio.fetchNextPage()} loading={bio.isFetchingNextPage}>
              Ältere Wochen laden
            </Button>
          )}
        </Stack>
      )}
    </>
  )
}
