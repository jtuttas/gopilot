// Types for the GoPilot web service contract, version 1 (docs/api-v1.md).
// Keep field names exactly as specified there.

/** Monday of a planning week as 'YYYY-MM-DD'. */
export type WeekString = string

export type AmpelColour = 'green' | 'yellow' | 'red' | 'none'
export type SignalKey = 'plan' | 'backlog' | 'reflection' | 'selfassessment'

export interface Meta {
  phase: string
  /** Minutes, 0 = not set. */
  duration: number
  /** 0 = not set, 1 Basis, 2 Standard, 3 Experte. */
  level: number
}

export interface Step {
  cmid: number
  courseid: number
  sectionid: number
  name: string
  modname: string
  url: string
  tracked: boolean
  completed: boolean
  manual: boolean
  /** Unix seconds, 0 = none. */
  expected: number
  overdue: boolean
  meta: Meta
}

export interface Signal {
  key: SignalKey
  active: boolean
  colour: AmpelColour
  value: number
  reason: string
}

export interface Ampel {
  courseid: number
  colour: AmpelColour
  timecomputed: number
  signals: Signal[]
}

export interface FeedbackEntry {
  id: number
  authorid: number
  authorname: string
  isteacher: boolean
  message: string
  timecreated: number
}

/** 0 = not set, 1 sicher, 2 geht so, 3 unsicher, 4 komme nicht klar. */
export type SelfAssessmentLevel = 0 | 1 | 2 | 3 | 4

export interface SelfAssessment {
  sectionid: number
  name: string
  level: number
  comment: string
}

export interface CourseWeek {
  courseid: number
  fullname: string
  reflection: string
  selfassessments: SelfAssessment[]
  feedback: FeedbackEntry[]
}

export interface Week {
  week: WeekString
  closed: boolean
  goal: string
  generalreflection: string
  reflectedat: number
  items: Step[]
  courses: CourseWeek[]
}

export interface Progress {
  completed: number
  total: number
}

export interface OverviewCourse {
  id: number
  shortname: string
  fullname: string
  canuse: boolean
  cancoach: boolean
  canconfigure: boolean
  progress: Progress
  ampel: Ampel
}

export interface Overview {
  userid: number
  fullname: string
  currentweek: WeekString
  courses: OverviewCourse[]
  week: Week
}

export interface Path {
  sectionid: number
  section: number
  name: string
  summary: string
  meta: Meta
  completed: number
  total: number
  steps: Step[]
}

export interface PathsResponse {
  course: { id: number; fullname: string }
  paths: Path[]
}

export interface Biography {
  total: number
  weeks: Week[]
}

export interface LerndokuThisWeek {
  /** Weekly goal, max. 200 characters; empty = none. */
  goal: string
  /** Planned steps of this course in the current week. */
  planned: number
  /** Of those, completed now. */
  completed: number
  /** Planned steps of all courses in the current week. */
  plannedall: number
}

export interface LerndokuLearner {
  userid: number
  fullname: string
  ampel: Ampel
  progress: Progress
  lastreflection: { week: WeekString; text: string }
  unreadfeedback: number
  /** Visible cohorts (global groups, e.g. classes), sorted by name; empty = none. */
  cohorts: { id: number; name: string }[]
  /** Current week: goal and planned steps of this course (plugin 0.5.0+; missing with older plugins). */
  thisweek?: LerndokuThisWeek
}

export interface Lerndoku {
  course: { id: number; fullname: string }
  currentweek: WeekString
  learners: LerndokuLearner[]
}

export interface LearnerWeek {
  week: WeekString
  closed: boolean
  goal: string
  generalreflection: string
  reflectedat: number
  items: Step[]
  reflection: string
  selfassessments: SelfAssessment[]
  feedback: FeedbackEntry[]
}

export interface LearnerDetail {
  learner: { id: number; fullname: string }
  ampel: Ampel
  weeks: LearnerWeek[]
}

export interface Thresholds {
  plangreen: number
  planyellow: number
  backlogyellow: number
  backlogred: number
  reflectionyellow: number
  reflectionred: number
}

export interface ConfigStep {
  cmid: number
  name: string
  modname: string
  meta: Meta
}

export interface ConfigPath {
  sectionid: number
  name: string
  meta: Meta
  steps: ConfigStep[]
}

export interface CourseConfig {
  courseid: number
  usedefaults: boolean
  thresholds: Thresholds
  defaults: Thresholds
  paths: ConfigPath[]
}

export interface PathMetaItem {
  type: 'section' | 'cm'
  id: number
  phase: string
  duration: number
  level: number
}

/** One-time link from local_gopilot_get_moodle_link (automatic Moodle login). */
export interface MoodleLink {
  url: string
  validuntil: number
}

export interface PushConfig {
  enabled: boolean
  publickey: string
}

export interface SuccessResponse {
  success: boolean
}

/** Result of core_completion_update_activity_completion_status_manually. */
export interface CompletionUpdateResponse {
  status: boolean
  warnings?: { item?: string; itemid?: number; warningcode: string; message: string }[]
}

export interface SaveReflectionCourse {
  courseid: number
  reflection: string
  selfassessments: { sectionid: number; level: number; comment: string }[]
}

export interface SaveReflectionParams {
  week: WeekString
  generalreflection: string
  courses: SaveReflectionCourse[]
}

export interface SaveCourseConfigParams {
  courseid: number
  usedefaults: boolean
  thresholds: Thresholds
}
