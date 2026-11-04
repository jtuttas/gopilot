import {
  Anchor,
  Badge,
  Button,
  Chip,
  Group,
  Progress,
  SegmentedControl,
  Select,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { IconAdjustments, IconSearch } from '@tabler/icons-react'
import { useMemo, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router'
import { useLerndoku, useOverview } from '../api/hooks'
import type { AmpelColour } from '../api/types'
import { AmpelBadge, SignalBadge } from '../components/Ampel'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { ampelDisplay, sortLearners } from '../lib/ampel'
import { weekLabel, weekTitle } from '../lib/week'

const COLOURS: AmpelColour[] = ['red', 'yellow', 'green', 'none']
/** Filter value for learners without a global group. */
const NO_COHORT = 'none'

function CoursePicker({ value }: { value?: number }) {
  const overview = useOverview()
  const navigate = useNavigate()
  const courses = overview.data?.courses.filter((c) => c.cancoach) ?? []
  return (
    <Select
      label="Kurs"
      placeholder="Kurs wählen"
      data={courses.map((c) => ({ value: String(c.id), label: c.fullname }))}
      value={value ? String(value) : null}
      onChange={(v) => v && navigate(`/lerndoku/${v}`)}
      allowDeselect={false}
      maw={480}
      w="100%"
    />
  )
}

export function LerndokuIndexPage() {
  const overview = useOverview()
  if (overview.isPending) return <Loading />
  if (overview.isError) return <ErrorAlert error={overview.error} />
  const courses = overview.data.courses.filter((c) => c.cancoach)
  if (courses.length === 1) return <Navigate to={`/lerndoku/${courses[0].id}`} replace />
  return (
    <>
      <PageHeader title="Lerndoku" description="Wähle einen Kurs, um die Lernentwicklung deiner Lernenden zu sehen." />
      {courses.length === 0 ? <Text c="dimmed">Du bist in keinem Kurs Lerncoach.</Text> : <CoursePicker />}
    </>
  )
}

export function LerndokuPage() {
  const courseid = Number(useParams().courseid)
  const overview = useOverview()
  const doku = useLerndoku(courseid)
  const [sortBy, setSortBy] = useState<'ampel' | 'name'>('ampel')
  const [colours, setColours] = useState<string[]>(COLOURS)
  const [search, setSearch] = useState('')
  const [cohort, setCohort] = useState<string | null>(null)

  const learners = useMemo(() => {
    const q = search.trim().toLowerCase()
    return sortLearners(doku.data?.learners ?? [], sortBy).filter(
      (l) =>
        colours.includes(l.ampel.colour) &&
        (!q || l.fullname.toLowerCase().includes(q)) &&
        (!cohort ||
          (cohort === NO_COHORT ? l.cohorts.length === 0 : l.cohorts.some((c) => String(c.id) === cohort))),
    )
  }, [doku.data, sortBy, colours, search, cohort])

  const cohortOptions = useMemo(() => {
    const all = doku.data?.learners ?? []
    const byId = new Map<number, { name: string; count: number }>()
    for (const l of all) {
      for (const c of l.cohorts) byId.set(c.id, { name: c.name, count: (byId.get(c.id)?.count ?? 0) + 1 })
    }
    const options = [...byId.entries()]
      .sort(([, a], [, b]) => a.name.localeCompare(b.name, 'de'))
      .map(([id, c]) => ({ value: String(id), label: `${c.name} (${c.count})` }))
    const without = all.filter((l) => l.cohorts.length === 0).length
    if (options.length > 0 && without > 0) options.push({ value: NO_COHORT, label: `Ohne globale Gruppe (${without})` })
    return options
  }, [doku.data])

  const canConfigure = overview.data?.courses.find((c) => c.id === courseid)?.canconfigure

  if (doku.isPending) return <Loading />
  if (doku.isError) return <ErrorAlert error={doku.error} onRetry={() => doku.refetch()} />
  const data = doku.data
  const counts = Object.fromEntries(COLOURS.map((c) => [c, data.learners.filter((l) => l.ampel.colour === c).length]))

  return (
    <>
      <PageHeader
        title="Lerndoku"
        description={
          <>
            {data.course.fullname} · {weekTitle(data.currentweek)}
          </>
        }
        actions={
          canConfigure && (
            <Button component={Link} to={`/kurseinstellungen/${courseid}`} variant="default" leftSection={<IconAdjustments size={16} />}>
              Kurseinstellungen
            </Button>
          )
        }
      />
      <Stack gap="sm" mb="md">
        <CoursePicker value={courseid} />
        <Group gap="md" align="flex-end" wrap="wrap">
          <TextInput
            label="Suche"
            placeholder="Name"
            leftSection={<IconSearch size={14} />}
            value={search}
            onChange={(e) => setSearch(e.currentTarget.value)}
            w={200}
          />
          {cohortOptions.length > 0 && (
            <Select
              label="Klasse"
              placeholder="Alle"
              data={cohortOptions}
              value={cohort}
              onChange={setCohort}
              clearable
              searchable
              w={240}
            />
          )}
          <Stack gap={4}>
            <Text size="sm" fw={500} id="sort-label">
              Sortierung
            </Text>
            <SegmentedControl
              aria-labelledby="sort-label"
              value={sortBy}
              onChange={(v) => setSortBy(v as 'ampel' | 'name')}
              data={[
                { value: 'ampel', label: 'Ampel (Rot zuerst)' },
                { value: 'name', label: 'Name' },
              ]}
            />
          </Stack>
          <Stack gap={4}>
            <Text size="sm" fw={500}>
              Ampelfarbe
            </Text>
            <Chip.Group multiple value={colours} onChange={setColours}>
              <Group gap={6}>
                {COLOURS.map((c) => (
                  <Chip key={c} value={c} color={ampelDisplay(c).color} size="sm" variant="light">
                    {ampelDisplay(c).label} ({counts[c]})
                  </Chip>
                ))}
              </Group>
            </Chip.Group>
          </Stack>
        </Group>
        <Text size="xs" c="dimmed">
          Die Förder-Ampel ist ein Hinweis auf möglichen Unterstützungsbedarf, keine Bewertung, und fließt nicht in Noten ein.
          Fahre über ein Signal oder fokussiere es, um die Begründung zu sehen.
        </Text>
      </Stack>

      <Table.ScrollContainer minWidth={960}>
        <Table striped highlightOnHover verticalSpacing="sm">
          <Table.Thead>
            <Table.Tr>
              <Table.Th>Ampel</Table.Th>
              <Table.Th>Name</Table.Th>
              <Table.Th>Klasse</Table.Th>
              <Table.Th>Signale</Table.Th>
              <Table.Th>Fortschritt</Table.Th>
              <Table.Th>Letzte Kursreflexion</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {learners.length === 0 && (
              <Table.Tr>
                <Table.Td colSpan={6}>
                  <Text size="sm" c="dimmed">
                    Keine Lernenden für diese Auswahl.
                  </Text>
                </Table.Td>
              </Table.Tr>
            )}
            {learners.map((l) => {
              const pct = l.progress.total ? Math.round((l.progress.completed / l.progress.total) * 100) : 0
              return (
                <Table.Tr key={l.userid}>
                  <Table.Td miw={130} style={{ whiteSpace: 'nowrap' }}>
                    <AmpelBadge colour={l.ampel.colour} size="sm" />
                  </Table.Td>
                  <Table.Td style={{ whiteSpace: 'nowrap' }}>
                    <Anchor component={Link} to={`/lerndoku/${courseid}/${l.userid}`} fw={600} size="sm">
                      {l.fullname}
                    </Anchor>
                  </Table.Td>
                  <Table.Td maw={180}>
                    {l.cohorts.length > 0 ? (
                      <Text size="sm">{l.cohorts.map((c) => c.name).join(', ')}</Text>
                    ) : (
                      <Text size="xs" c="dimmed">
                        keine
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td miw={230}>
                    <Group gap={4} wrap="wrap">
                      {l.ampel.signals.map((s) => (
                        <SignalBadge key={s.key} signal={s} />
                      ))}
                    </Group>
                  </Table.Td>
                  <Table.Td miw={140}>
                    <Text size="xs" c="dimmed">
                      {l.progress.completed}/{l.progress.total} ({pct} %)
                    </Text>
                    <Progress value={pct} size="xs" aria-label={`Fortschritt ${pct} Prozent`} />
                  </Table.Td>
                  <Table.Td maw={320}>
                    {l.lastreflection.week ? (
                      <>
                        <Badge size="xs" variant="default" mb={2}>
                          {weekLabel(l.lastreflection.week)}
                        </Badge>
                        <Text size="xs" lineClamp={2}>
                          {l.lastreflection.text}
                        </Text>
                      </>
                    ) : (
                      <Text size="xs" c="dimmed">
                        noch keine
                      </Text>
                    )}
                  </Table.Td>
                </Table.Tr>
              )
            })}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
    </>
  )
}
