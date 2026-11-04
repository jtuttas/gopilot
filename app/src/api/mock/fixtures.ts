// In-memory fixtures for mock mode (VITE_MOCK=1). They mirror the dev Moodle:
// courses GOPILOT-LF5 and GOPILOT-LF8, learners Ada/Ben/Can Azubi and the
// teacher Lena Lehrkraft. Dates are relative to the current week so the
// data always looks "live".

import type { Meta, Thresholds } from '../types'
import { addDays, addWeeks, mondayOf } from '../../lib/week'

export interface MockUser {
  id: number
  username: string
  fullname: string
  /** Global groups (cohorts), e.g. the class. */
  cohorts?: { id: number; name: string }[]
}

export interface MockStep {
  cmid: number
  sectionid: number
  name: string
  modname: string
  manual: boolean
  /** Expected date relative to the current Monday in days, null = none. */
  expectedOffset: number | null
}

export interface MockSection {
  id: number
  section: number
  name: string
  summary: string
  steps: MockStep[]
}

export interface MockCourse {
  id: number
  shortname: string
  fullname: string
  sections: MockSection[]
  learners: number[]
  coaches: number[]
  configurers: number[]
  teachingweeks: string[]
  usedefaults: boolean
  thresholds: Thresholds
}

export interface MockWeekData {
  goal: string
  generalreflection: string
  reflectedat: number
  cmids: number[]
  /** courseid -> Kursreflexion */
  reflections: Record<number, string>
  /** courseid -> sectionid -> self-assessment */
  selfassessments: Record<number, Record<number, { level: number; comment: string }>>
}

export interface MockFeedback {
  id: number
  userid: number
  courseid: number
  week: string
  authorid: number
  message: string
  timecreated: number
}

export interface MockDb {
  now: () => number
  currentWeek: string
  users: MockUser[]
  courses: MockCourse[]
  /** userid -> cmid -> completion time (unix) */
  completions: Record<number, Record<number, number>>
  /** userid -> week -> data */
  weeks: Record<number, Record<string, MockWeekData>>
  feedback: MockFeedback[]
  /** 's<sectionid>' or 'c<cmid>' -> meta */
  meta: Record<string, Meta>
  nextId: number
}

export const DEFAULT_THRESHOLDS: Thresholds = {
  plangreen: 70,
  planyellow: 40,
  backlogyellow: 2,
  backlogred: 4,
  reflectionyellow: 1,
  reflectionred: 2,
}

export const LENA = 2
export const ADA = 11
export const BEN = 12
export const CAN = 13
export const LF5 = 101
export const LF8 = 102

/** Unix seconds of a day relative to the current Monday, at hh:00 local time. */
export function dayTime(currentWeek: string, offsetDays: number, hour = 12): number {
  const [y, m, d] = addDays(currentWeek, offsetDays).split('-').map(Number)
  return Math.floor(new Date(y, m - 1, d, hour).getTime() / 1000)
}

function step(
  cmid: number,
  sectionid: number,
  name: string,
  modname: string,
  expectedOffset: number | null,
): MockStep {
  return { cmid, sectionid, name, modname, manual: modname === 'page' || modname === 'url', expectedOffset }
}

function emptyWeek(): MockWeekData {
  return { goal: '', generalreflection: '', reflectedat: 0, cmids: [], reflections: {}, selfassessments: {} }
}

export function createMockDb(today: Date = new Date()): MockDb {
  const C = mondayOf(today)
  const W = (n: number) => addWeeks(C, n)
  const t = (offsetDays: number, hour = 12) => dayTime(C, offsetDays, hour)

  const courses: MockCourse[] = [
    {
      id: LF5,
      shortname: 'GOPILOT-LF5',
      fullname: 'LF5 Software zur Verwaltung von Daten anpassen',
      learners: [ADA, BEN, CAN],
      coaches: [LENA],
      configurers: [LENA],
      teachingweeks: [W(-6), W(-5), W(-3), W(-2), W(-1), W(0), W(3), W(4), W(5)],
      usedefaults: true,
      thresholds: { ...DEFAULT_THRESHOLDS },
      sections: [
        {
          id: 1001,
          section: 1,
          name: 'LS 5.1 Datenbank für den Kundenservice entwerfen',
          summary: 'Die Kundenservice-Abteilung braucht eine Datenbank für Anfragen. Du entwirfst das Datenmodell.',
          steps: [
            step(5001, 1001, 'Auftrag: Kundenservice-Datenbank', 'page', -18),
            step(5002, 1001, 'Informationen: Entity-Relationship-Modell', 'page', -11),
            step(5003, 1001, 'ER-Modell erstellen', 'assign', -10),
            step(5004, 1001, 'Wissenscheck ER-Modell', 'quiz', -5),
            step(5005, 1001, 'Relationales Modell ableiten', 'assign', -3),
          ],
        },
        {
          id: 1002,
          section: 2,
          name: 'LS 5.2 SQL-Abfragen für Auswertungen',
          summary: 'Die Teamleitung möchte monatliche Auswertungen. Du schreibst die passenden SQL-Abfragen.',
          steps: [
            step(5011, 1002, 'Informationen: SELECT-Grundlagen', 'page', 1),
            step(5012, 1002, 'SQL-Quiz: Filtern und Sortieren', 'quiz', 4),
            step(5013, 1002, 'Informationen: JOINs', 'page', 9),
            step(5014, 1002, 'Auswertungen für die Teamleitung schreiben', 'assign', 11),
            step(5015, 1002, 'Ergebnisse präsentieren', 'assign', null),
          ],
        },
      ],
    },
    {
      id: LF8,
      shortname: 'GOPILOT-LF8',
      fullname: 'LF8 Daten systemübergreifend bereitstellen',
      learners: [ADA, BEN, CAN],
      coaches: [LENA],
      configurers: [LENA],
      teachingweeks: [W(-1), W(0), W(6), W(7)],
      usedefaults: false,
      thresholds: { ...DEFAULT_THRESHOLDS, plangreen: 80, backlogred: 3 },
      sections: [
        {
          id: 2001,
          section: 1,
          name: 'LS 8.1 Schnittstellen analysieren',
          summary: 'Ein Partnerunternehmen liefert Bestelldaten. Du analysierst die Schnittstelle.',
          steps: [
            step(8001, 2001, 'Auftrag: Bestelldaten übernehmen', 'page', -5),
            step(8002, 2001, 'Schnittstellenbeschreibung lesen', 'page', -3),
            step(8003, 2001, 'Schnittstellen-Steckbrief', 'assign', 4),
          ],
        },
        {
          id: 2002,
          section: 2,
          name: 'LS 8.2 Daten im JSON-Format austauschen',
          summary: 'Du stellst Daten im JSON-Format für andere Systeme bereit.',
          steps: [
            step(8011, 2002, 'Informationen: JSON', 'page', 8),
            step(8012, 2002, 'JSON-Export programmieren', 'assign', 15),
            step(8013, 2002, 'Zusatz: JSON-Schema', 'page', null),
          ],
        },
      ],
    },
  ]

  const weeks: MockDb['weeks'] = { [ADA]: {}, [BEN]: {}, [CAN]: {} }

  // Ada: plans, completes and reflects regularly -> green.
  weeks[ADA][W(-3)] = {
    ...emptyWeek(),
    goal: 'Ich verstehe, was ein ER-Modell ist.',
    cmids: [5001],
    generalreflection: 'Guter Start, der Auftrag ist klar.',
    reflectedat: t(-17, 15),
    reflections: { [LF5]: 'Auftrag gelesen und Fragen notiert.' },
    selfassessments: { [LF5]: { 1001: { level: 1, comment: '' } } },
  }
  weeks[ADA][W(-2)] = {
    ...emptyWeek(),
    goal: 'ER-Modell für den Kundenservice fertigstellen.',
    cmids: [5002, 5003],
    generalreflection: 'Ich habe mir die Zeit gut eingeteilt.',
    reflectedat: t(-10, 16),
    reflections: { [LF5]: 'Das ER-Modell ist fertig, die Kardinalitäten waren knifflig.' },
    selfassessments: { [LF5]: { 1001: { level: 2, comment: 'Kardinalitäten noch einmal üben' } } },
  }
  weeks[ADA][W(-1)] = {
    ...emptyWeek(),
    goal: 'LS 5.1 abschließen und mit LF8 starten.',
    cmids: [5004, 5005, 8001, 8002],
    generalreflection: 'Viel geschafft, im Betrieb war es ruhig.',
    reflectedat: t(-2, 18),
    reflections: {
      [LF5]: 'Relationales Modell abgeleitet, der Wissenscheck lief gut.',
      [LF8]: 'Die Schnittstellenbeschreibung habe ich verstanden.',
    },
    selfassessments: {
      [LF5]: { 1001: { level: 1, comment: '' } },
      [LF8]: { 2001: { level: 2, comment: 'HTTP-Statuscodes nachlesen' } },
    },
  }
  weeks[ADA][W(0)] = {
    ...emptyWeek(),
    goal: 'SQL-Grundlagen sicher anwenden.',
    cmids: [5011, 5012, 8003],
  }

  // Ben: half of the plan done, unsure in LS 5.2, missing LF8 reflection -> yellow.
  weeks[BEN][W(-2)] = {
    ...emptyWeek(),
    goal: 'ER-Modell abgeben.',
    cmids: [5002, 5003],
    generalreflection: 'Lief okay.',
    reflectedat: t(-9, 20),
    reflections: { [LF5]: 'ER-Modell abgegeben.' },
    selfassessments: { [LF5]: { 1001: { level: 2, comment: '' } } },
  }
  weeks[BEN][W(-1)] = {
    ...emptyWeek(),
    goal: 'LS 5.1 fertig machen und mit SQL anfangen.',
    cmids: [5004, 5005, 5011, 5012],
    generalreflection: 'Ich hatte wenig Zeit, weil ich krank war.',
    reflectedat: t(-1, 19),
    reflections: { [LF5]: 'Wissenscheck geschafft, das relationale Modell fehlt noch.' },
    selfassessments: { [LF5]: { 1001: { level: 2, comment: '' }, 1002: { level: 3, comment: 'JOINs verstehe ich noch nicht.' } } },
  }
  weeks[BEN][W(0)] = { ...emptyWeek(), goal: 'Relationales Modell nachholen.', cmids: [5005, 5013] }

  // Can: planned but nothing done, no reflections, backlog -> red.
  weeks[CAN][W(-1)] = { ...emptyWeek(), goal: 'Aufholen.', cmids: [5002, 5003, 5004] }

  const completions: MockDb['completions'] = {
    [ADA]: { 5001: t(-18), 5002: t(-12), 5003: t(-10), 5004: t(-6), 5005: t(-3), 8001: t(-5), 8002: t(-4), 5011: t(0, 10) },
    [BEN]: { 5001: t(-17), 5002: t(-12), 5003: t(-9), 5004: t(-5), 5011: t(-4), 8001: t(-4), 8002: t(-2) },
    [CAN]: { 5001: t(-16) },
  }

  const feedback: MockFeedback[] = [
    {
      id: 1, userid: ADA, courseid: LF5, week: W(-1), authorid: LENA,
      message: 'Sehr sauber abgeleitet! Schau dir für LS 5.2 gleich die JOINs an, das passt gut zu deinem Modell.',
      timecreated: t(0, 8),
    },
    {
      id: 2, userid: ADA, courseid: LF5, week: W(-1), authorid: ADA,
      message: 'Danke, mache ich diese Woche.', timecreated: t(0, 9),
    },
    {
      id: 3, userid: BEN, courseid: LF5, week: W(-1), authorid: LENA,
      message: 'Gute Besserung! Wo genau hakt es bei den JOINs? Wir können am Donnerstag zusammen ein Beispiel durchgehen.',
      timecreated: t(0, 8),
    },
  ]

  const meta: MockDb['meta'] = {
    s1001: { phase: '', duration: 360, level: 2 },
    s1002: { phase: '', duration: 480, level: 2 },
    c5001: { phase: 'Informieren', duration: 20, level: 1 },
    c5002: { phase: 'Informieren', duration: 45, level: 1 },
    c5003: { phase: 'Durchführen', duration: 120, level: 2 },
    c5004: { phase: 'Kontrollieren', duration: 20, level: 2 },
    c5005: { phase: 'Durchführen', duration: 90, level: 3 },
    c5011: { phase: 'Informieren', duration: 45, level: 1 },
    c5012: { phase: 'Kontrollieren', duration: 20, level: 2 },
    c5014: { phase: 'Durchführen', duration: 180, level: 2 },
  }

  return {
    now: () => Math.floor(Date.now() / 1000),
    currentWeek: C,
    users: [
      { id: LENA, username: 'lena', fullname: 'Lena Lehrkraft' },
      { id: ADA, username: 'ada', fullname: 'Ada Azubi', cohorts: [{ id: 1, name: 'FIAE24A' }] },
      { id: BEN, username: 'ben', fullname: 'Ben Azubi', cohorts: [{ id: 2, name: 'FIAE24B' }] },
      { id: CAN, username: 'can', fullname: 'Can Azubi' },
    ],
    courses,
    completions,
    weeks,
    feedback,
    meta,
    nextId: 100,
  }
}

export function emptyMockWeek(): MockWeekData {
  return emptyWeek()
}
