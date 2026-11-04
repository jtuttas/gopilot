import {
  Badge,
  Button,
  Card,
  Divider,
  Group,
  Radio,
  SegmentedControl,
  Stack,
  Text,
  Textarea,
  TextInput,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconDeviceFloppy, IconEye, IconLock, IconUsers } from '@tabler/icons-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { useOverview, useSaveReflection, useWeek } from '../api/hooks'
import type { SaveReflectionCourse, Week } from '../api/types'
import { FeedbackThread } from '../components/FeedbackThread'
import { Notice } from '../components/Notice'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { StepItem } from '../components/StepItem'
import { SELF_ASSESSMENT_LEVELS } from '../lib/ampel'
import { addWeeks, formatDateTime, isMonday, relativeWeekLabel, weekTitle } from '../lib/week'

type CourseState = Record<number, SaveReflectionCourse>

function initialCourses(week: Week): CourseState {
  return Object.fromEntries(
    week.courses.map((c) => [
      c.courseid,
      {
        courseid: c.courseid,
        reflection: c.reflection,
        selfassessments: c.selfassessments.map((s) => ({ sectionid: s.sectionid, level: s.level, comment: s.comment })),
      },
    ]),
  )
}

function ReflectionEditor({ week, userid, editable }: { week: Week; userid: number; editable: boolean }) {
  const [general, setGeneral] = useState(week.generalreflection)
  const [courses, setCourses] = useState<CourseState>(() => initialCourses(week))
  const save = useSaveReflection()

  const updateCourse = (courseid: number, patch: Partial<SaveReflectionCourse>) =>
    setCourses((prev) => ({ ...prev, [courseid]: { ...prev[courseid], ...patch } }))

  const updateSa = (courseid: number, sectionid: number, patch: { level?: number; comment?: string }) =>
    setCourses((prev) => ({
      ...prev,
      [courseid]: {
        ...prev[courseid],
        selfassessments: prev[courseid].selfassessments.map((s) => (s.sectionid === sectionid ? { ...s, ...patch } : s)),
      },
    }))

  const submit = () =>
    save.mutate(
      {
        week: week.week,
        generalreflection: general.trim(),
        courses: week.courses.map((c) => ({
          ...courses[c.courseid],
          reflection: courses[c.courseid].reflection.trim(),
          selfassessments: courses[c.courseid].selfassessments.map((s) => ({ ...s, comment: s.comment.trim() })),
        })),
      },
      {
        onSuccess: () =>
          notifications.show({
            color: 'teal',
            title: 'Reflexion gespeichert',
            message: 'Deine Lehrkräfte können sie jetzt lesen und dir antworten.',
          }),
        onError: (e) => notifications.show({ color: 'red', title: 'Nicht gespeichert', message: errorMessage(e) }),
      },
    )

  return (
    <Stack gap="md">
      {!editable && (
        <Notice color="gray" icon={<IconLock />}>
          Diese Woche kann nicht mehr reflektiert werden. Du kannst aber weiterhin auf Coaching-Feedback antworten.
        </Notice>
      )}
      {week.reflectedat > 0 && (
        <Text size="xs" c="dimmed">
          Zuletzt gespeichert am {formatDateTime(week.reflectedat)}
        </Text>
      )}

      <Card withBorder radius="md" padding="md">
        <Stack gap="xs">
          <Title order={3} size="h4">
            Allgemeine Reflexion
          </Title>
          <Notice color="blue" icon={<IconUsers />} p="xs">
            Sichtbar für alle deine Lehrkräfte
          </Notice>
          <Textarea
            label="Wie lief deine Woche insgesamt?"
            description="Was hast du geschafft? Was hat geholfen oder gebremst? Was nimmst du dir vor?"
            autosize
            minRows={3}
            value={general}
            onChange={(e) => setGeneral(e.currentTarget.value)}
            disabled={!editable}
          />
        </Stack>
      </Card>

      {week.courses.map((c) => {
        const state = courses[c.courseid]
        const items = week.items.filter((s) => s.courseid === c.courseid)
        return (
          <Card key={c.courseid} withBorder radius="md" padding="md">
            <Stack gap="sm">
              <Group justify="space-between" wrap="nowrap" gap="xs" align="flex-start">
                <Title order={3} size="h4">
                  {c.fullname}
                </Title>
                <Badge size="xs" variant="outline" color={c.isschoolweek ? 'blue' : 'gray'} style={{ flexShrink: 0 }}>
                  {c.isschoolweek ? 'Schulwoche' : 'Betriebswoche'}
                </Badge>
              </Group>

              {items.length > 0 && (
                <div>
                  <Text size="xs" c="dimmed">
                    Geplant: {items.filter((s) => s.completed).length} von {items.length} erledigt
                  </Text>
                  {items.map((s) => (
                    <StepItem key={s.cmid} step={s} canTick={false} showMeta={false} />
                  ))}
                </div>
              )}

              <Notice color="grape" icon={<IconEye />} p="xs">
                Nur für die Lehrkräfte dieses Kurses sichtbar
              </Notice>
              <Textarea
                label="Kursreflexion"
                description="Wie bist du in diesem Kurs vorangekommen?"
                autosize
                minRows={2}
                value={state.reflection}
                onChange={(e) => updateCourse(c.courseid, { reflection: e.currentTarget.value })}
                disabled={!editable}
              />

              {c.selfassessments.length > 0 && (
                <Stack gap="sm">
                  <Text size="sm" fw={600}>
                    Selbsteinschätzung
                  </Text>
                  {c.selfassessments.map((sa) => {
                    const current = state.selfassessments.find((x) => x.sectionid === sa.sectionid)!
                    return (
                      <Stack key={sa.sectionid} gap={6}>
                        <Radio.Group
                          label={sa.name}
                          description="Wie sicher fühlst du dich in diesem Lernpfad?"
                          value={current.level ? String(current.level) : ''}
                          onChange={(v) => updateSa(c.courseid, sa.sectionid, { level: Number(v) })}
                        >
                          <Group gap="md" mt={6}>
                            {SELF_ASSESSMENT_LEVELS.map((l) => (
                              <Radio key={l.value} value={String(l.value)} label={l.label} disabled={!editable} />
                            ))}
                          </Group>
                        </Radio.Group>
                        <TextInput
                          label="Kommentar (optional)"
                          size="xs"
                          value={current.comment}
                          onChange={(e) => updateSa(c.courseid, sa.sectionid, { comment: e.currentTarget.value })}
                          disabled={!editable}
                        />
                      </Stack>
                    )
                  })}
                </Stack>
              )}

              <Divider label="Coaching-Feedback" labelPosition="left" />
              <FeedbackThread
                entries={c.feedback}
                courseid={c.courseid}
                learnerid={userid}
                week={week.week}
                viewer="learner"
              />
            </Stack>
          </Card>
        )
      })}

      {editable && (
        <Group justify="flex-end">
          <Button leftSection={<IconDeviceFloppy size={16} />} onClick={submit} loading={save.isPending}>
            Reflexion speichern
          </Button>
        </Group>
      )}
    </Stack>
  )
}

export function ReflectionPage() {
  const params = useParams()
  const navigate = useNavigate()
  const overview = useOverview()
  const current = overview.data?.currentweek
  const week = params.week && isMonday(params.week) ? params.week : current
  const weekQuery = useWeek(week)

  if (overview.isError) return <ErrorAlert error={overview.error} onRetry={() => overview.refetch()} />
  if (!current || !week || !overview.data) return <Loading />

  const options = [addWeeks(current, -1), current]
  const editable = options.includes(week)

  return (
    <>
      <PageHeader title="Reflexion" description={weekTitle(week)} />
      <SegmentedControl
        mb="md"
        fullWidth
        value={editable ? week : ''}
        onChange={(w) => navigate(`/reflexion/${w}`)}
        data={options.map((w) => ({ value: w, label: relativeWeekLabel(w, current) }))}
        aria-label="Woche wählen"
      />
      {weekQuery.isError ? (
        <ErrorAlert error={weekQuery.error} onRetry={() => weekQuery.refetch()} />
      ) : weekQuery.isPending ? (
        <Loading />
      ) : (
        <ReflectionEditor key={week} week={weekQuery.data} userid={overview.data.userid} editable={editable} />
      )}
    </>
  )
}
