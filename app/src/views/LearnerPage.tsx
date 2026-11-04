import {
  Anchor,
  Badge,
  Breadcrumbs,
  Card,
  Divider,
  Grid,
  Group,
  Paper,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { IconWorld } from '@tabler/icons-react'
import { Link, useParams } from 'react-router'
import { useLearner, useLerndoku } from '../api/hooks'
import type { LearnerWeek } from '../api/types'
import { AmpelSummary } from '../components/Ampel'
import { FeedbackThread } from '../components/FeedbackThread'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { StepItem } from '../components/StepItem'
import { selfAssessmentLabel, SELF_ASSESSMENT_LEVELS, ampelDisplay } from '../lib/ampel'
import { formatDateTime, weekTitle } from '../lib/week'

function LearnerWeekCard({ week, courseid, userid }: { week: LearnerWeek; courseid: number; userid: number }) {
  const done = week.items.filter((s) => s.completed).length
  return (
    <Card withBorder radius="md" padding="md">
      <Stack gap="sm">
        <Group justify="space-between" wrap="wrap" gap="xs">
          <Title order={3} size="h4">
            {weekTitle(week.week)}
          </Title>
          <Group gap={6}>
            {!week.closed && (
              <Badge variant="light" size="sm">
                laufend
              </Badge>
            )}
            {week.reflectedat > 0 && (
              <Text size="xs" c="dimmed">
                reflektiert am {formatDateTime(week.reflectedat)}
              </Text>
            )}
          </Group>
        </Group>

        <Grid gutter="md">
          <Grid.Col span={{ base: 12, md: 6 }}>
            <Stack gap="sm">
              <div>
                <Text size="xs" c="dimmed">
                  Wochenziel
                </Text>
                <Text size="sm">{week.goal || '–'}</Text>
              </div>
              <Paper withBorder p="xs" radius="md" bg="var(--mantine-color-gray-light)">
                <Group gap={6} mb={2}>
                  <IconWorld size={14} aria-hidden />
                  <Text size="xs" fw={600}>
                    Allgemeine Reflexion
                  </Text>
                  <Text size="xs" c="dimmed">
                    kursübergreifend · nur lesen
                  </Text>
                </Group>
                <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                  {week.generalreflection || '–'}
                </Text>
              </Paper>
              <div>
                <Text size="xs" c="dimmed">
                  Geplante Lernschritte in diesem Kurs ({done} von {week.items.length} erledigt
                  {week.closed ? ', Stand Wochenende' : ''})
                </Text>
                {week.items.length === 0 ? (
                  <Text size="sm" c="dimmed">
                    keine
                  </Text>
                ) : (
                  week.items.map((s) => <StepItem key={s.cmid} step={s} canTick={false} showMeta={false} />)
                )}
              </div>
            </Stack>
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 6 }}>
            <Stack gap="sm">
              <div>
                <Text size="xs" c="dimmed">
                  Kursreflexion
                </Text>
                <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                  {week.reflection || 'Keine Kursreflexion.'}
                </Text>
              </div>
              {week.selfassessments.length > 0 && (
                <div>
                  <Text size="xs" c="dimmed">
                    Selbsteinschätzung
                  </Text>
                  {week.selfassessments.map((s) => {
                    const colour = SELF_ASSESSMENT_LEVELS.find((l) => l.value === s.level)?.colour ?? 'none'
                    return (
                      <Group key={s.sectionid} gap={6} wrap="nowrap" align="flex-start">
                        <Badge size="sm" color={ampelDisplay(colour).color} c={colour === 'yellow' ? 'yellow.9' : undefined} variant="light" style={{ flexShrink: 0 }} styles={{ label: { textTransform: 'none' } }}>
                          {selfAssessmentLabel(s.level)}
                        </Badge>
                        <Text size="sm">
                          {s.name}
                          {s.comment && (
                            <Text span size="sm" c="dimmed">
                              {' '}
                              – „{s.comment}“
                            </Text>
                          )}
                        </Text>
                      </Group>
                    )
                  })}
                </div>
              )}
              <Divider label="Coaching-Feedback" labelPosition="left" />
              <FeedbackThread entries={week.feedback} courseid={courseid} learnerid={userid} week={week.week} viewer="coach" />
            </Stack>
          </Grid.Col>
        </Grid>
      </Stack>
    </Card>
  )
}

export function LearnerPage() {
  const params = useParams()
  const courseid = Number(params.courseid)
  const userid = Number(params.userid)
  const learner = useLearner(courseid, userid)
  const doku = useLerndoku(courseid)

  if (learner.isPending) return <Loading />
  if (learner.isError) return <ErrorAlert error={learner.error} onRetry={() => learner.refetch()} />
  const data = learner.data

  return (
    <>
      <Breadcrumbs mb="xs">
        <Anchor component={Link} to={`/lerndoku/${courseid}`} size="sm">
          Lerndoku{doku.data ? ` ${doku.data.course.fullname}` : ''}
        </Anchor>
        <Text size="sm">{data.learner.fullname}</Text>
      </Breadcrumbs>
      <PageHeader title={data.learner.fullname} />
      <Card withBorder radius="md" padding="md" mb="md">
        <AmpelSummary ampel={data.ampel} audience="coach" />
      </Card>
      <Stack gap="md">
        {data.weeks.length === 0 ? (
          <Text c="dimmed">Noch keine Wochen mit Plan, Ziel oder Reflexion.</Text>
        ) : (
          data.weeks.map((w) => <LearnerWeekCard key={w.week} week={w} courseid={courseid} userid={userid} />)
        )}
      </Stack>
    </>
  )
}
