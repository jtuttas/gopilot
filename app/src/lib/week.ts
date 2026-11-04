// Week helpers. A planning week is identified by its Monday as 'YYYY-MM-DD'
// (api-v1.md). Calendar arithmetic is done on UTC dates built from the local
// calendar day, so daylight saving changes never shift a day.

const DAY_MS = 86_400_000

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

/** Local calendar day of a Date as UTC midnight timestamp. */
function utcDay(d: Date): number {
  return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())
}

function fromUtc(ms: number): string {
  const d = new Date(ms)
  return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`
}

/** Parses 'YYYY-MM-DD' into a UTC midnight timestamp. */
function parseUtc(iso: string): number {
  const [y, m, d] = iso.split('-').map(Number)
  return Date.UTC(y, m - 1, d)
}

/** Local calendar day as 'YYYY-MM-DD'. */
export function toIsoDate(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

/** Monday (0) … Sunday (6) of a local date. */
export function weekdayIndex(d: Date): number {
  return (d.getDay() + 6) % 7
}

/** Monday of the week containing `d`, as 'YYYY-MM-DD'. */
export function mondayOf(d: Date = new Date()): string {
  return fromUtc(utcDay(d) - weekdayIndex(d) * DAY_MS)
}

/** Shifts a week by `n` weeks. */
export function addWeeks(week: string, n: number): string {
  return fromUtc(parseUtc(week) + n * 7 * DAY_MS)
}

/** Adds `n` days to a 'YYYY-MM-DD' date. */
export function addDays(iso: string, n: number): string {
  return fromUtc(parseUtc(iso) + n * DAY_MS)
}

/** True if 'YYYY-MM-DD' is a valid date that falls on a Monday. */
export function isMonday(iso: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return false
  const ms = parseUtc(iso)
  return !Number.isNaN(ms) && fromUtc(ms) === iso && new Date(ms).getUTCDay() === 1
}

/** ISO 8601 calendar week and its week-based year. */
export function isoWeek(iso: string): { week: number; year: number } {
  const ms = parseUtc(iso)
  const day = (new Date(ms).getUTCDay() + 6) % 7
  const thursday = ms + (3 - day) * DAY_MS
  const year = new Date(thursday).getUTCFullYear()
  const jan4 = Date.UTC(year, 0, 4)
  const jan4Monday = jan4 - ((new Date(jan4).getUTCDay() + 6) % 7) * DAY_MS
  return { week: Math.floor((thursday - jan4Monday) / (7 * DAY_MS)) + 1, year }
}

/** 'DD.MM.YYYY' for a 'YYYY-MM-DD' date. */
export function formatIsoDate(iso: string, withYear = true): string {
  const [y, m, d] = iso.split('-')
  return withYear ? `${d}.${m}.${y}` : `${d}.${m}.`
}

/** 'KW 45' */
export function weekLabel(week: string): string {
  return `KW ${isoWeek(week).week}`
}

/** '02.11.–08.11.2026' */
export function weekRangeLabel(week: string): string {
  return `${formatIsoDate(week, false)}–${formatIsoDate(addDays(week, 6))}`
}

/** 'KW 45 · 02.11.–08.11.2026' */
export function weekTitle(week: string): string {
  return `${weekLabel(week)} · ${weekRangeLabel(week)}`
}

/** Unix seconds -> 'DD.MM.YYYY' (local time). */
export function formatDate(unix: number): string {
  if (!unix) return ''
  const d = new Date(unix * 1000)
  return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`
}

/** Unix seconds -> 'DD.MM.YYYY, HH:MM' (local time). */
export function formatDateTime(unix: number): string {
  if (!unix) return ''
  const d = new Date(unix * 1000)
  return `${formatDate(unix)}, ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** First year of the school year (Aug–Jul) that contains `d`. */
export function schoolYearOf(d: Date = new Date()): number {
  return d.getMonth() >= 7 ? d.getFullYear() : d.getFullYear() - 1
}

/**
 * All Mondays of weeks that overlap the school year starting 1 August
 * `startYear` and ending 31 July `startYear + 1`.
 */
export function schoolYearWeeks(startYear: number): string[] {
  const first = mondayOf(new Date(startYear, 7, 1))
  const last = Date.UTC(startYear + 1, 6, 31)
  const weeks: string[] = []
  for (let w = first; parseUtc(w) <= last; w = addWeeks(w, 1)) weeks.push(w)
  return weeks
}

/** Relation of a week to the current week, for labels. */
export function relativeWeekLabel(week: string, current: string): string {
  const diff = Math.round((parseUtc(week) - parseUtc(current)) / (7 * DAY_MS))
  if (diff === 0) return 'Diese Woche'
  if (diff === 1) return 'Nächste Woche'
  if (diff === -1) return 'Letzte Woche'
  return weekLabel(week)
}
