// Each course gets a stable accent colour so learners can tell at a glance which course a
// Lernschritt belongs to. Red, yellow and teal are left out: they carry Ampel meaning.

const PALETTE = ['blue', 'grape', 'orange', 'cyan', 'pink', 'indigo', 'lime', 'violet'] as const

/**
 * Short label for compact badges: a leading code like "LF5" if the name has one
 * ("LF5 - Software …" → "LF5"), otherwise the name cut to 18 characters.
 */
export function shortCourseName(name: string): string {
  const code = /^([A-Za-zÄÖÜäöü]{1,6}\s?\d{1,3}[a-z]?)\b/.exec(name.trim())
  if (code) return code[1]
  const t = name.trim()
  return t.length > 18 ? `${t.slice(0, 17)}…` : t
}

/** Mantine colour per course id, in the order of `courses` (the overview order). */
export function courseColours(courses: { id: number }[]): Map<number, string> {
  return new Map(courses.map((c, i) => [c.id, PALETTE[i % PALETTE.length]]))
}
