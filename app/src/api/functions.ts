import { call } from './client'
import type {
  Biography,
  CompletionUpdateResponse,
  CourseConfig,
  FeedbackEntry,
  LearnerDetail,
  Lerndoku,
  Overview,
  PathMetaItem,
  PathsResponse,
  PushConfig,
  SaveCourseConfigParams,
  SaveReflectionParams,
  SuccessResponse,
  Week,
  WeekString,
} from './types'

/**
 * Typed wrappers, one per web service function of the contract.
 * Exported as an object so tests can spy on single functions.
 */
export const api = {
  getOverview: () => call<Overview>('local_gopilot_get_overview'),

  getPaths: (courseid: number) => call<PathsResponse>('local_gopilot_get_paths', { courseid }),

  getWeek: (week: WeekString) => call<Week>('local_gopilot_get_week', { week }),

  savePlan: (week: WeekString, goal: string, cmids: number[]) =>
    call<Week>('local_gopilot_save_plan', { week, goal, cmids }),

  saveReflection: (p: SaveReflectionParams) =>
    call<Week>('local_gopilot_save_reflection', {
      week: p.week,
      generalreflection: p.generalreflection,
      courses: p.courses.map((c) => ({
        courseid: c.courseid,
        reflection: c.reflection,
        selfassessments: c.selfassessments.map((s) => ({
          sectionid: s.sectionid,
          level: s.level,
          comment: s.comment,
        })),
      })),
    }),

  getBiography: (page: number, perpage = 10) =>
    call<Biography>('local_gopilot_get_biography', { page, perpage }),

  getLerndoku: (courseid: number) => call<Lerndoku>('local_gopilot_get_lerndoku', { courseid }),

  getLearner: (courseid: number, userid: number) =>
    call<LearnerDetail>('local_gopilot_get_learner', { courseid, userid }),

  addFeedback: (courseid: number, userid: number, week: WeekString, message: string) =>
    call<FeedbackEntry>('local_gopilot_add_feedback', { courseid, userid, week, message }),

  getCourseConfig: (courseid: number) =>
    call<CourseConfig>('local_gopilot_get_courseconfig', { courseid }),

  saveCourseConfig: (p: SaveCourseConfigParams) =>
    call<CourseConfig>('local_gopilot_save_courseconfig', {
      courseid: p.courseid,
      teachingweeks: p.teachingweeks,
      usedefaults: p.usedefaults,
      thresholds: { ...p.thresholds },
    }),

  savePathMeta: (courseid: number, items: PathMetaItem[]) =>
    call<SuccessResponse>('local_gopilot_save_pathmeta', {
      courseid,
      items: items.map((i) => ({ ...i })),
    }),

  getPushConfig: () => call<PushConfig>('local_gopilot_get_push_config'),

  registerPush: (endpoint: string, p256dh: string, auth: string) =>
    call<SuccessResponse>('local_gopilot_register_push', { endpoint, p256dh, auth }),

  unregisterPush: (endpoint: string) =>
    call<SuccessResponse>('local_gopilot_unregister_push', { endpoint }),

  /** Ticks or unticks a step with manual completion. */
  setStepCompletion: (cmid: number, completed: boolean) =>
    call<CompletionUpdateResponse>('core_completion_update_activity_completion_status_manually', {
      cmid,
      completed,
    }),
}
