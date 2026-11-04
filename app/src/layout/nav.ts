import {
  IconAdjustments,
  IconCalendarWeek,
  IconClipboardList,
  IconDots,
  IconHome,
  IconMessageCircle,
  IconRoute,
  IconSettings,
  IconTimeline,
  type Icon,
} from '@tabler/icons-react'
import type { OverviewCourse } from '../api/types'

export interface NavItem {
  to: string
  label: string
  icon: Icon
  /** Match only the exact path. */
  end?: boolean
}

export interface Roles {
  isLearner: boolean
  isCoach: boolean
  isConfigurer: boolean
}

export function rolesOf(courses: OverviewCourse[] | undefined): Roles {
  const list = courses ?? []
  return {
    isLearner: list.some((c) => c.canuse),
    isCoach: list.some((c) => c.cancoach),
    isConfigurer: list.some((c) => c.canconfigure),
  }
}

const START: NavItem = { to: '/', label: 'Start', icon: IconHome, end: true }
const PATHS: NavItem = { to: '/lernpfade', label: 'Lernpfade', icon: IconRoute }
const PLAN: NavItem = { to: '/plan', label: 'Wochenplan', icon: IconCalendarWeek }
const REFLECTION: NavItem = { to: '/reflexion', label: 'Reflexion', icon: IconMessageCircle }
const BIO: NavItem = { to: '/lernbiografie', label: 'Lernbiografie', icon: IconTimeline }
const LERNDOKU: NavItem = { to: '/lerndoku', label: 'Lerndoku', icon: IconClipboardList }
const COURSESETTINGS: NavItem = { to: '/kurseinstellungen', label: 'Kurseinstellungen', icon: IconAdjustments }
const SETTINGS: NavItem = { to: '/einstellungen', label: 'Einstellungen', icon: IconSettings }
const MORE: NavItem = { to: '/mehr', label: 'Mehr', icon: IconDots }

/** Sidebar on desktop. */
export function sidebarItems(r: Roles): { learner: NavItem[]; coach: NavItem[]; general: NavItem[] } {
  return {
    learner: r.isLearner ? [START, PATHS, PLAN, REFLECTION, BIO] : [START],
    coach: [...(r.isCoach ? [LERNDOKU] : []), ...(r.isConfigurer ? [COURSESETTINGS] : [])],
    general: [SETTINGS],
  }
}

/** Bottom navigation on mobile (max. five entries). */
export function bottomItems(r: Roles): NavItem[] {
  if (r.isLearner) return [START, PATHS, { ...PLAN, label: 'Plan' }, REFLECTION, MORE]
  if (r.isCoach) return [START, LERNDOKU, MORE]
  return [START, MORE]
}

/** Entries of the mobile "Mehr" page. */
export function moreItems(r: Roles): NavItem[] {
  return [
    ...(r.isLearner ? [BIO] : []),
    ...(r.isCoach ? [LERNDOKU] : []),
    ...(r.isConfigurer ? [COURSESETTINGS] : []),
    SETTINGS,
  ]
}
