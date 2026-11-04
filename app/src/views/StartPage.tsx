import {
  Anchor,
  Badge,
  Button,
  Card,
  Divider,
  Group,
  Progress,
  SimpleGrid,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { IconCalendarPlus, IconClipboardList, IconMessageCircle, IconTarget } from '@tabler/icons-react'
import { Link } from 'react-router'
import { useOverview, useWeek } from '../api/hooks'
import type { Overview, OverviewCourse } from '../api/types'
import { AmpelSummary } from '../components/Ampel'
import { Notice } from '../components/Notice'
import { ErrorAlert, Loading } from '../components/QueryState'
import { StepItem } from '../components/StepItem'
import { rolesOf } from '../layout/nav'
import { addWeeks, weekdayIndex, weekTitle } from '../lib/week'

function percent(done: number, total: number) {
  return total > 0 ? Math.round((done / total) * 100) : 0
}

export function CourseProgress({ completed, total }: { completed: number; total: number }) {
  const pct = percent(completed, total)
  return (
    <Stack gap={4}>
      <Group justify="space-between">
        <Text size="xs" c="dimmed">
          Kursfortschritt
        </Text>
        <Text size="xs" c="dimmed">
          {completed} von {total} Lernschritten ({pct} %)
        </Text>
      </Group>
      <Progress value={pct} size="sm" aria-label={`Kursfortschritt ${pct} Prozent`} />
    </Stack>
  )
}

function Prompts({ overview }: { overview: Overview }) {
  const current = overview.currentweek
  const previous = addWeeks(current, -1)
  const prev = useWeek(previous)
  const week = overview.week
  const isWeekend = weekdayIndex(new Date()) >= 4
  const nothingPlanned = week.items.length === 0 && !week.goal
  const prevOpen =
    prev.data &&
    prev.data.reflectedat === 0 &&
    (prev.data.items.length > 0 || prev.data.goal !== '' || prev.data.courses.some((c) => c.isschoolweek))

  return (
    <Stack gap="sm">
      {nothingPlanned && (
        <Notice color="blue" icon={<IconCalendarPlus />} title="Woche planen">
          <Stack gap="xs" align="flex-start">
            <Text size="sm">Für diese Woche hast du noch nichts geplant. Such dir ein paar Lernschritte und ein Wochenziel aus.</Text>
            <Button component={Link} to={`/plan/${current}`} size="xs">
              Woche planen
            </Button>
          </Stack>
        </Notice>
      )}
      {isWeekend && week.reflectedat === 0 && (
        <Notice color="grape" icon={<IconMessageCircle />} title="Woche reflektieren">
          <Stack gap="xs" align="flex-start">
            <Text size="sm">Die Woche ist fast geschafft. Nimm dir ein paar Minuten für deine Reflexion.</Text>
            <Button component={Link} to={`/reflexion/${current}`} size="xs" color="grape">
              Woche reflektieren
            </Button>
          </Stack>
        </Notice>
      )}
      {prevOpen && (
        <Notice color="grape" icon={<IconMessageCircle />} title="Letzte Woche reflektieren">
          <Stack gap="xs" align="flex-start">
            <Text size="sm">Deine Reflexion zur letzten Woche fehlt noch. Du kannst sie jetzt nachholen.</Text>
            <Button component={Link} to={`/reflexion/${previous}`} size="xs" color="grape" variant="light">
              Letzte Woche reflektieren
            </Button>
          </Stack>
        </Notice>
      )}
    </Stack>
  )
}

function CurrentWeekCard({ overview }: { overview: Overview }) {
  const week = overview.week
  const names = new Map(overview.courses.map((c) => [c.id, c.fullname]))
  const done = week.items.filter((s) => s.completed).length
  return (
    <Card withBorder radius="md" padding="md">
      <Group justify="space-between" mb="xs" wrap="wrap">
        <Title order={3} size="h4">
          Diese Woche
        </Title>
        <Anchor component={Link} to={`/plan/${week.week}`} size="sm">
          Plan bearbeiten
        </Anchor>
      </Group>
      <Group gap="xs" wrap="nowrap" align="flex-start" mb="sm">
        <IconTarget size={18} aria-hidden style={{ flexShrink: 0, marginTop: 2 }} />
        <div>
          <Text size="xs" c="dimmed">
            Wochenziel
          </Text>
          <Text size="sm">{week.goal || 'Noch kein Wochenziel formuliert.'}</Text>
        </div>
      </Group>
      <Divider mb="xs" />
      {week.items.length === 0 ? (
        <Text size="sm" c="dimmed">
          Noch keine Lernschritte geplant.
        </Text>
      ) : (
        <>
          <Text size="xs" c="dimmed" mb={4}>
            {done} von {week.items.length} geplanten Lernschritten erledigt
          </Text>
          <Stack gap={0}>
            {week.items.map((s) => (
              <StepItem key={s.cmid} step={s} canTick={!week.closed} showMeta={false} courseName={names.get(s.courseid)} />
            ))}
          </Stack>
        </>
      )}
    </Card>
  )
}

function CourseCard({ course }: { course: OverviewCourse }) {
  return (
    <Card withBorder radius="md" padding="md">
      <Stack gap="sm">
        <Group justify="space-between" align="flex-start" wrap="nowrap" gap="xs">
          <Title order={4} size="sm">
            {course.fullname}
          </Title>
          <Badge size="xs" variant="outline" color={course.isschoolweek ? 'blue' : 'gray'} style={{ flexShrink: 0 }}>
            {course.isschoolweek ? 'Schulwoche' : 'Betriebswoche'}
          </Badge>
        </Group>
        <CourseProgress {...course.progress} />
        <AmpelSummary ampel={course.ampel} audience="learner" />
        <Anchor component={Link} to={`/lernpfade/${course.id}`} size="sm">
          Zu den Lernpfaden
        </Anchor>
      </Stack>
    </Card>
  )
}

export function StartPage() {
  const overview = useOverview()
  if (overview.isPending) return <Loading />
  if (overview.isError) return <ErrorAlert error={overview.error} onRetry={() => overview.refetch()} />
  const data = overview.data
  const roles = rolesOf(data.courses)
  const firstName = data.fullname.split(' ')[0]
  const learnCourses = data.courses.filter((c) => c.canuse)
  const coachCourses = data.courses.filter((c) => c.cancoach)

  return (
    <Stack gap="md">
      <div>
        <Title order={2}>Hallo {firstName}!</Title>
        <Text size="sm" c="dimmed">
          {weekTitle(data.currentweek)}
        </Text>
      </div>

      {roles.isLearner && (
        <>
          <Prompts overview={data} />
          <CurrentWeekCard overview={data} />
          <Title order={3} size="h4">
            Deine Kurse
          </Title>
          <Text size="xs" c="dimmed" mt={-10}>
            Die Förder-Ampel ist ein Hinweis für dich, keine Bewertung. Nur du und die Lehrkräfte des Kurses sehen sie.
          </Text>
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            {learnCourses.map((c) => (
              <CourseCard key={c.id} course={c} />
            ))}
          </SimpleGrid>
        </>
      )}

      {coachCourses.length > 0 && (
        <>
          <Title order={3} size="h4">
            Deine Kurse als Lerncoach
          </Title>
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            {coachCourses.map((c) => (
              <Card key={c.id} withBorder radius="md" padding="md">
                <Stack gap="xs">
                  <Text fw={600} size="sm">
                    {c.fullname}
                  </Text>
                  <Group gap="xs">
                    <Button component={Link} to={`/lerndoku/${c.id}`} size="xs" leftSection={<IconClipboardList size={14} />}>
                      Lerndoku
                    </Button>
                    {c.canconfigure && (
                      <Button component={Link} to={`/kurseinstellungen/${c.id}`} size="xs" variant="default">
                        Kurseinstellungen
                      </Button>
                    )}
                  </Group>
                </Stack>
              </Card>
            ))}
          </SimpleGrid>
        </>
      )}

      {data.courses.length === 0 && (
        <Notice color="gray" title="Keine Kurse">
          Du bist in keinem Kurs eingeschrieben, der GoPilot nutzt.
        </Notice>
      )}
    </Stack>
  )
}
