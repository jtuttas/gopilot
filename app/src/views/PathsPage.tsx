import {
  Accordion,
  Anchor,
  Breadcrumbs,
  Card,
  Group,
  Progress,
  RingProgress,
  SimpleGrid,
  Stack,
  Switch,
  Text,
} from '@mantine/core'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useOverview, usePaths } from '../api/hooks'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { MetaBadges, StepItem } from '../components/StepItem'
import { CourseProgress } from './StartPage'

export function CourseListPage() {
  const overview = useOverview()
  if (overview.isPending) return <Loading />
  if (overview.isError) return <ErrorAlert error={overview.error} onRetry={() => overview.refetch()} />
  const courses = overview.data.courses.filter((c) => c.canuse)
  return (
    <>
      <PageHeader title="Lernpfade" description="Wähle einen Kurs. Jeder Lernpfad ist eine Lernsituation aus Moodle." />
      {courses.length === 0 ? (
        <Text c="dimmed">Keine Kurse mit Lernpfaden.</Text>
      ) : (
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {courses.map((c) => (
            <Card key={c.id} withBorder radius="md" component={Link} to={`/lernpfade/${c.id}`} padding="md">
              <Stack gap="sm">
                <Text fw={600}>{c.fullname}</Text>
                <CourseProgress {...c.progress} />
              </Stack>
            </Card>
          ))}
        </SimpleGrid>
      )}
    </>
  )
}

export function CoursePathsPage() {
  const courseid = Number(useParams().courseid)
  const paths = usePaths(courseid)
  const [hideDone, setHideDone] = useState(false)

  if (paths.isPending) return <Loading />
  if (paths.isError) return <ErrorAlert error={paths.error} onRetry={() => paths.refetch()} />
  const { course } = paths.data
  const firstOpen = paths.data.paths.find((p) => p.completed < p.total) ?? paths.data.paths[0]

  return (
    <>
      <Breadcrumbs mb="xs">
        <Anchor component={Link} to="/lernpfade" size="sm">
          Lernpfade
        </Anchor>
        <Text size="sm">{course.fullname}</Text>
      </Breadcrumbs>
      <PageHeader
        title={course.fullname}
        actions={
          <Switch
            label="Erledigte ausblenden"
            checked={hideDone}
            onChange={(e) => setHideDone(e.currentTarget.checked)}
          />
        }
      />
      <Accordion
        variant="separated"
        radius="md"
        multiple
        defaultValue={firstOpen ? [String(firstOpen.sectionid)] : []}
      >
        {paths.data.paths.map((p) => {
          const pct = p.total ? Math.round((p.completed / p.total) * 100) : 0
          const steps = hideDone ? p.steps.filter((s) => !s.completed) : p.steps
          return (
            <Accordion.Item key={p.sectionid} value={String(p.sectionid)}>
              <Accordion.Control>
                <Group wrap="nowrap" gap="sm">
                  <RingProgress
                    size={44}
                    thickness={5}
                    sections={[{ value: pct, color: pct === 100 ? 'teal' : 'blue' }]}
                    label={
                      <Text ta="center" size="10px" fw={700}>
                        {pct}%
                      </Text>
                    }
                    aria-hidden
                  />
                  <Stack gap={2} style={{ minWidth: 0 }}>
                    <Text fw={600} size="sm">
                      {p.name}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {p.completed} von {p.total} Lernschritten erledigt
                    </Text>
                  </Stack>
                </Group>
              </Accordion.Control>
              <Accordion.Panel>
                <Stack gap="xs">
                  {p.summary && (
                    <Text size="sm" c="dimmed">
                      {p.summary}
                    </Text>
                  )}
                  <MetaBadges meta={p.meta} />
                  <Progress value={pct} size="xs" aria-label={`Fortschritt ${p.name}: ${pct} Prozent`} />
                  <Stack gap={0}>
                    {steps.length === 0 ? (
                      <Text size="sm" c="dimmed">
                        Alle Lernschritte erledigt.
                      </Text>
                    ) : (
                      steps.map((s) => <StepItem key={s.cmid} step={s} />)
                    )}
                  </Stack>
                </Stack>
              </Accordion.Panel>
            </Accordion.Item>
          )
        })}
      </Accordion>
    </>
  )
}
