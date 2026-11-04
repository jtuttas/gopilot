import {
  Accordion,
  Autocomplete,
  Button,
  Card,
  Group,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Switch,
  Tabs,
  Text,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconAdjustments, IconDeviceFloppy, IconRoute } from '@tabler/icons-react'
import { useMemo, useState } from 'react'
import { Navigate, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { useCourseConfig, useOverview, useSaveCourseConfig, useSavePathMeta } from '../api/hooks'
import type { CourseConfig, Meta, PathMetaItem, Thresholds } from '../api/types'
import { Notice } from '../components/Notice'
import { PageHeader } from '../components/PageHeader'
import { ErrorAlert, Loading } from '../components/QueryState'
import { modLabel } from '../lib/format'
import { LEVEL_LABELS } from '../lib/ampel'
import { validateThresholds } from '../lib/thresholds'

const PHASES = ['Informieren', 'Planen', 'Entscheiden', 'Durchführen', 'Kontrollieren', 'Bewerten', 'Reflektieren']

// ------------------------------------------------------------ Schwellwerte

const FIELDS: { key: keyof Thresholds; label: string; description: string; max?: number }[] = [
  { key: 'plangreen', label: 'Planerfüllung: Grün ab (%)', description: 'Anteil erledigter geplanter Lernschritte', max: 100 },
  { key: 'planyellow', label: 'Planerfüllung: Gelb ab (%)', description: 'darunter Rot', max: 100 },
  { key: 'backlogyellow', label: 'Pfadrückstand: Gelb ab', description: 'überfällige Lernschritte' },
  { key: 'backlogred', label: 'Pfadrückstand: Rot ab', description: 'überfällige Lernschritte' },
  { key: 'reflectionyellow', label: 'Fehlende Reflexion: Gelb ab', description: 'geplante Wochen in Folge ohne Kursreflexion' },
  { key: 'reflectionred', label: 'Fehlende Reflexion: Rot ab', description: 'geplante Wochen in Folge ohne Kursreflexion' },
]

function ThresholdForm({
  usedefaults,
  thresholds,
  defaults,
  onChange,
}: {
  usedefaults: boolean
  thresholds: Thresholds
  defaults: Thresholds
  onChange: (usedefaults: boolean, t: Thresholds) => void
}) {
  const shown = usedefaults ? defaults : thresholds
  const errors = usedefaults ? {} : validateThresholds(thresholds)
  return (
    <Stack gap="md">
      <Switch
        label="Standardwerte verwenden"
        description="Die Standardwerte gelten für alle Kurse. Schalte aus, um eigene Werte für diesen Kurs festzulegen."
        checked={usedefaults}
        onChange={(e) => onChange(e.currentTarget.checked, thresholds)}
      />
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        {FIELDS.map((f) => (
          <NumberInput
            key={f.key}
            label={f.label}
            description={f.description}
            value={shown[f.key]}
            min={0}
            max={f.max}
            allowDecimal={false}
            allowNegative={false}
            disabled={usedefaults}
            error={errors[f.key]}
            onChange={(v) => onChange(usedefaults, { ...thresholds, [f.key]: typeof v === 'number' ? v : Number(v) || 0 })}
          />
        ))}
      </SimpleGrid>
      <Text size="xs" c="dimmed">
        Die Stufen der Selbsteinschätzung sind fest: „unsicher“ ergibt Gelb, „komme nicht klar“ Rot.
      </Text>
    </Stack>
  )
}

// ------------------------------------------------------------ Pfad-Metadaten

type MetaState = Record<string, Meta>
const metaKey = (type: 'section' | 'cm', id: number) => `${type}:${id}`

function MetaFields({ value, onChange, label }: { value: Meta; onChange: (m: Meta) => void; label: string }) {
  return (
    <SimpleGrid cols={{ base: 1, xs: 3 }} spacing="xs">
      <Autocomplete
        label="Phase"
        aria-label={`Phase: ${label}`}
        size="xs"
        data={PHASES}
        value={value.phase}
        onChange={(v) => onChange({ ...value, phase: v })}
        placeholder="keine"
      />
      <NumberInput
        label="Dauer (Minuten)"
        aria-label={`Dauer in Minuten: ${label}`}
        size="xs"
        min={0}
        step={5}
        allowDecimal={false}
        allowNegative={false}
        value={value.duration || ''}
        placeholder="keine Angabe"
        onChange={(v) => onChange({ ...value, duration: typeof v === 'number' ? v : Number(v) || 0 })}
      />
      <Select
        label="Niveau"
        aria-label={`Niveau: ${label}`}
        size="xs"
        data={Object.entries(LEVEL_LABELS).map(([v, l]) => ({ value: v, label: l }))}
        value={String(value.level)}
        onChange={(v) => onChange({ ...value, level: Number(v ?? 0) })}
        allowDeselect={false}
      />
    </SimpleGrid>
  )
}

function PathMetaEditor({ config }: { config: CourseConfig }) {
  const initial = useMemo(() => {
    const s: MetaState = {}
    for (const p of config.paths) {
      s[metaKey('section', p.sectionid)] = p.meta
      for (const st of p.steps) s[metaKey('cm', st.cmid)] = st.meta
    }
    return s
  }, [config])
  const [state, setState] = useState<MetaState>(initial)
  const save = useSavePathMeta()
  const set = (key: string, m: Meta) => setState((prev) => ({ ...prev, [key]: m }))
  const changed = Object.keys(state).filter((k) => JSON.stringify(state[k]) !== JSON.stringify(initial[k]))

  const submit = () => {
    const items: PathMetaItem[] = changed.map((k) => {
      const [type, id] = k.split(':')
      return { type: type as 'section' | 'cm', id: Number(id), ...state[k], phase: state[k].phase.trim() }
    })
    save.mutate(
      { courseid: config.courseid, items },
      {
        onSuccess: () => notifications.show({ color: 'teal', message: 'Pfad-Metadaten gespeichert.' }),
        onError: (e) => notifications.show({ color: 'red', title: 'Nicht gespeichert', message: errorMessage(e) }),
      },
    )
  }

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Phase, Dauer und Niveau helfen Lernenden bei der Wochenplanung. Alle Angaben sind optional.
      </Text>
      <Accordion variant="separated" multiple>
        {config.paths.map((p) => (
          <Accordion.Item key={p.sectionid} value={String(p.sectionid)}>
            <Accordion.Control>
              <Text fw={600} size="sm">
                {p.name}
              </Text>
            </Accordion.Control>
            <Accordion.Panel>
              <Stack gap="md">
                <Card withBorder padding="xs" radius="md" bg="var(--mantine-color-gray-light)">
                  <Text size="xs" fw={600} mb={4}>
                    Lernpfad
                  </Text>
                  <MetaFields label={p.name} value={state[metaKey('section', p.sectionid)]} onChange={(m) => set(metaKey('section', p.sectionid), m)} />
                </Card>
                {p.steps.map((st) => (
                  <div key={st.cmid}>
                    <Text size="sm" fw={500}>
                      {st.name}{' '}
                      <Text span size="xs" c="dimmed">
                        ({modLabel(st.modname)})
                      </Text>
                    </Text>
                    <MetaFields label={st.name} value={state[metaKey('cm', st.cmid)]} onChange={(m) => set(metaKey('cm', st.cmid), m)} />
                  </div>
                ))}
              </Stack>
            </Accordion.Panel>
          </Accordion.Item>
        ))}
      </Accordion>
      <Group justify="flex-end">
        <Text size="sm" c="dimmed">
          {changed.length} Änderung{changed.length === 1 ? '' : 'en'}
        </Text>
        <Button leftSection={<IconDeviceFloppy size={16} />} onClick={submit} loading={save.isPending} disabled={changed.length === 0}>
          Metadaten speichern
        </Button>
      </Group>
    </Stack>
  )
}

// ------------------------------------------------------------ Page

function SettingsEditor({ config }: { config: CourseConfig }) {
  const [usedefaults, setUsedefaults] = useState(config.usedefaults)
  const [thresholds, setThresholds] = useState(config.thresholds)
  const save = useSaveCourseConfig()
  const invalid = !usedefaults && Object.keys(validateThresholds(thresholds)).length > 0

  const submit = () =>
    save.mutate(
      { courseid: config.courseid, usedefaults, thresholds },
      {
        onSuccess: () => notifications.show({ color: 'teal', message: 'Kurseinstellungen gespeichert.' }),
        onError: (e) => notifications.show({ color: 'red', title: 'Nicht gespeichert', message: errorMessage(e) }),
      },
    )

  const saveBar = (
    <Group justify="flex-end" mt="md">
      {invalid && (
        <Text size="sm" c="red">
          Bitte korrigiere die Schwellwerte.
        </Text>
      )}
      <Button leftSection={<IconDeviceFloppy size={16} />} onClick={submit} loading={save.isPending} disabled={invalid}>
        Speichern
      </Button>
    </Group>
  )

  return (
    <Tabs defaultValue="thresholds" keepMounted={false}>
      <Tabs.List mb="md">
        <Tabs.Tab value="thresholds" leftSection={<IconAdjustments size={16} />}>
          Schwellwerte
        </Tabs.Tab>
        <Tabs.Tab value="meta" leftSection={<IconRoute size={16} />}>
          Pfad-Metadaten
        </Tabs.Tab>
      </Tabs.List>
      <Tabs.Panel value="thresholds">
        <ThresholdForm
          usedefaults={usedefaults}
          thresholds={thresholds}
          defaults={config.defaults}
          onChange={(u, t) => {
            setUsedefaults(u)
            setThresholds(t)
          }}
        />
        {saveBar}
      </Tabs.Panel>
      <Tabs.Panel value="meta">
        <PathMetaEditor config={config} />
      </Tabs.Panel>
    </Tabs>
  )
}

export function CourseSettingsPage() {
  const params = useParams()
  const navigate = useNavigate()
  const overview = useOverview()
  const courseid = params.courseid ? Number(params.courseid) : undefined
  const config = useCourseConfig(courseid)

  if (overview.isPending) return <Loading />
  if (overview.isError) return <ErrorAlert error={overview.error} />
  const courses = overview.data.courses.filter((c) => c.canconfigure)
  if (!courseid && courses.length === 1) return <Navigate to={`/kurseinstellungen/${courses[0].id}`} replace />
  const course = courses.find((c) => c.id === courseid)

  return (
    <>
      <PageHeader title="Kurseinstellungen" description={course?.fullname} />
      <Select
        label="Kurs"
        placeholder="Kurs wählen"
        data={courses.map((c) => ({ value: String(c.id), label: c.fullname }))}
        value={courseid ? String(courseid) : null}
        onChange={(v) => v && navigate(`/kurseinstellungen/${v}`)}
        allowDeselect={false}
        maw={480}
        mb="md"
      />
      {courses.length === 0 && <Notice color="gray">Du darfst in keinem Kurs Einstellungen ändern.</Notice>}
      {courseid &&
        (config.isPending ? (
          <Loading />
        ) : config.isError ? (
          <ErrorAlert error={config.error} onRetry={() => config.refetch()} />
        ) : (
          <Card withBorder radius="md" padding="md">
            <Title order={3} size="h4" mb="sm" hiddenFrom="sm">
              {course?.fullname}
            </Title>
            <SettingsEditor key={courseid} config={config.data} />
          </Card>
        ))}
    </>
  )
}
