import {
  Accordion,
  Badge,
  Box,
  Button,
  Card,
  Checkbox,
  Group,
  SegmentedControl,
  Stack,
  Switch,
  Text,
  Textarea,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconDeviceFloppy, IconInfoCircle } from '@tabler/icons-react'
import { useQueries } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { api } from '../api/functions'
import { queryKeys, useOverview, useSavePlan, useWeek } from '../api/hooks'
import type { PathsResponse, Week } from '../api/types'
import { CourseHeading } from '../components/CourseHeading'
import { Notice } from '../components/Notice'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { courseColours, shortCourseName } from '../lib/courseColours'
import { metaText, modLabel } from '../lib/format'
import { addWeeks, formatDate, isMonday, relativeWeekLabel, weekTitle } from '../lib/week'

interface EditorProps {
  week: Week
  paths: PathsResponse[]
  colours: Map<number, string>
}

function PlanEditor({ week, paths, colours }: EditorProps) {
  const [goal, setGoal] = useState(week.goal)
  const [selected, setSelected] = useState<Set<number>>(() => new Set(week.items.map((s) => s.cmid)))
  const [showDone, setShowDone] = useState(false)
  const save = useSavePlan()
  const readOnly = week.closed

  const countOf = (p: PathsResponse) =>
    p.paths.reduce((n, x) => n + x.steps.filter((s) => selected.has(s.cmid)).length, 0)

  const toggle = (cmid: number, on: boolean) =>
    setSelected((prev) => {
      const next = new Set(prev)
      if (on) next.add(cmid)
      else next.delete(cmid)
      return next
    })

  const submit = () => {
    // Keep Moodle order: course, path, step.
    const ordered = paths.flatMap((p) => p.paths.flatMap((x) => x.steps.map((s) => s.cmid))).filter((id) => selected.has(id))
    const unknown = [...selected].filter((id) => !ordered.includes(id))
    save.mutate(
      { week: week.week, goal: goal.trim(), cmids: [...ordered, ...unknown] },
      {
        onSuccess: () => notifications.show({ color: 'teal', title: 'Gespeichert', message: 'Dein Wochenplan ist gespeichert.' }),
        onError: (e) => notifications.show({ color: 'red', title: 'Nicht gespeichert', message: errorMessage(e) }),
      },
    )
  }

  return (
    <Stack gap="md">
      {readOnly && (
        <Notice color="gray" icon={<IconInfoCircle />}>
          Diese Woche ist abgeschlossen und kann nicht mehr geändert werden.
        </Notice>
      )}
      <Textarea
        label="Wochenziel"
        description="Was willst du diese Woche erreichen? Dein Wochenziel sehen alle deine Lehrkräfte."
        placeholder="z. B. Ich kann einfache SQL-Abfragen mit WHERE und ORDER BY schreiben."
        autosize
        minRows={2}
        maxLength={1000}
        value={goal}
        onChange={(e) => setGoal(e.currentTarget.value)}
        disabled={readOnly}
      />

      <Group justify="space-between" wrap="wrap">
        <Title order={3} size="h4">
          Lernschritte auswählen
        </Title>
        <Switch label="Erledigte anzeigen" checked={showDone} onChange={(e) => setShowDone(e.currentTarget.checked)} />
      </Group>

      {paths.map((p) => {
        const color = colours.get(p.course.id) ?? 'blue'
        const count = countOf(p)
        return (
          <Card
            key={p.course.id}
            withBorder
            radius="md"
            padding="sm"
            style={{ borderLeft: `4px solid var(--mantine-color-${color}-6)` }}
            component="section"
            aria-label={`Kurs ${p.course.fullname}`}
          >
            <Box mb="sm">
              <CourseHeading
                name={p.course.fullname}
                color={color}
                right={
                  <Badge
                    color={color}
                    variant={count > 0 ? 'filled' : 'light'}
                    style={{ flexShrink: 0 }}
                    styles={{ label: { textTransform: 'none' } }}
                  >
                    {count} ausgewählt
                  </Badge>
                }
              />
            </Box>
            <Accordion multiple variant="contained" radius="md" defaultValue={p.paths.filter((x) => x.completed < x.total).map((x) => String(x.sectionid))}>
              {p.paths.map((path) => {
                const visible = path.steps.filter((s) => showDone || !s.completed || selected.has(s.cmid))
                const count = path.steps.filter((s) => selected.has(s.cmid)).length
                return (
                  <Accordion.Item key={path.sectionid} value={String(path.sectionid)}>
                    <Accordion.Control>
                      <Group justify="space-between" wrap="nowrap" gap="xs">
                        <Text size="sm" fw={500}>
                          {path.name}
                        </Text>
                        {count > 0 && (
                          <Badge size="sm" circle color={color} aria-label={`${count} ausgewählt`}>
                            {count}
                          </Badge>
                        )}
                      </Group>
                    </Accordion.Control>
                    <Accordion.Panel>
                      <Stack gap="sm">
                        {visible.length === 0 && (
                          <Text size="sm" c="dimmed">
                            Alle Lernschritte dieses Lernpfads sind erledigt.
                          </Text>
                        )}
                        {visible.map((s) => (
                          <Checkbox
                            key={s.cmid}
                            color={color}
                            checked={selected.has(s.cmid)}
                            onChange={(e) => toggle(s.cmid, e.currentTarget.checked)}
                            disabled={readOnly}
                            label={
                              <Text size="sm" component="span">
                                {s.name}
                                {s.completed && (
                                  <Text span size="xs" c="teal">
                                    {' '}
                                    (erledigt)
                                  </Text>
                                )}
                              </Text>
                            }
                            description={
                              <>
                                {[modLabel(s.modname), metaText(s.meta)].filter(Boolean).join(' · ')}
                                {s.expected > 0 && (
                                  <Text span size="xs" c={s.overdue ? 'orange.8' : 'dimmed'} fw={s.overdue ? 600 : undefined}>
                                    {' · '}
                                    {s.overdue ? 'Soll-Termin war ' : 'Soll-Termin '}
                                    {formatDate(s.expected)}
                                  </Text>
                                )}
                              </>
                            }
                          />
                        ))}
                      </Stack>
                    </Accordion.Panel>
                  </Accordion.Item>
                )
              })}
            </Accordion>
          </Card>
        )
      })}

      <Card withBorder radius="md" padding="sm" pos="sticky" bottom="calc(var(--app-shell-footer-offset, 0px) + 8px)" style={{ zIndex: 5 }} shadow="sm">
        <Group justify="space-between" wrap="nowrap">
          <Stack gap={4}>
            <Text size="sm">
              {selected.size} {selected.size === 1 ? 'Lernschritt' : 'Lernschritte'} ausgewählt
            </Text>
            {paths.length > 1 && (
              <Group gap={4}>
                {paths.map((p) => (
                  <Badge
                    key={p.course.id}
                    size="xs"
                    variant="light"
                    color={colours.get(p.course.id) ?? 'blue'}
                    styles={{ label: { textTransform: 'none' } }}
                    maw={180}
                    title={p.course.fullname}
                  >
                    {shortCourseName(p.course.fullname)}: {countOf(p)}
                  </Badge>
                ))}
              </Group>
            )}
          </Stack>
          <Button leftSection={<IconDeviceFloppy size={16} />} onClick={submit} loading={save.isPending} disabled={readOnly}>
            Plan speichern
          </Button>
        </Group>
      </Card>
    </Stack>
  )
}

export function PlanPage() {
  const params = useParams()
  const navigate = useNavigate()
  const overview = useOverview()
  const current = overview.data?.currentweek
  const week = params.week && isMonday(params.week) ? params.week : current
  const weekQuery = useWeek(week)
  const courses = overview.data?.courses.filter((c) => c.canuse) ?? []
  const pathQueries = useQueries({
    queries: courses.map((c) => ({ queryKey: queryKeys.paths(c.id), queryFn: () => api.getPaths(c.id) })),
  })

  if (overview.isError) return <ErrorAlert error={overview.error} onRetry={() => overview.refetch()} />
  if (!current || !week) return <Loading />

  const options = [current, addWeeks(current, 1)]
  if (!options.includes(week)) options.push(week)
  const pathsError = pathQueries.find((q) => q.isError)?.error

  return (
    <>
      <PageHeader title="Wochenplan" description={weekTitle(week)} />
      <SegmentedControl
        mb="md"
        fullWidth
        value={week}
        onChange={(w) => navigate(`/plan/${w}`)}
        data={options.map((w) => ({ value: w, label: relativeWeekLabel(w, current) }))}
        aria-label="Woche wählen"
      />
      {weekQuery.isError ? (
        <ErrorAlert error={weekQuery.error} onRetry={() => weekQuery.refetch()} />
      ) : pathsError ? (
        <ErrorAlert error={pathsError} />
      ) : weekQuery.isPending || pathQueries.some((q) => q.isPending) ? (
        <Loading />
      ) : (
        <PlanEditor
          key={week}
          week={weekQuery.data}
          paths={pathQueries.map((q) => q.data!).filter(Boolean)}
          colours={courseColours(overview.data?.courses ?? [])}
        />
      )}
    </>
  )
}
