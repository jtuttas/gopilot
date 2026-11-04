// Display mapping for the Förder-Ampel. The Ampel is a hint, not a grade
// (ADR 0003): wording stays supportive and colour is never the only cue.

import type { AmpelColour, LerndokuLearner, Signal, SignalKey } from '../api/types'

export interface AmpelDisplay {
  /** Mantine colour name. */
  color: string
  /** Short colour word, always shown next to the colour. */
  label: string
  /** Supportive sentence for learners. */
  learnerHint: string
  /** Neutral sentence for coaches. */
  coachHint: string
  /** Sort rank, lower = needs attention first. */
  rank: number
}

const DISPLAY: Record<AmpelColour, AmpelDisplay> = {
  red: {
    color: 'red',
    label: 'Rot',
    learnerHint: 'Hier lohnt sich ein Gespräch mit deiner Lehrkraft. Gemeinsam findet ihr den nächsten Schritt.',
    coachHint: 'Unterstützung sinnvoll',
    rank: 0,
  },
  yellow: {
    color: 'yellow',
    label: 'Gelb',
    learnerHint: 'Schau hier noch einmal hin. Ein kleiner Schritt reicht oft schon.',
    coachHint: 'Im Blick behalten',
    rank: 1,
  },
  green: {
    color: 'teal',
    label: 'Grün',
    learnerHint: 'Du bist gut unterwegs. Weiter so!',
    coachHint: 'Läuft',
    rank: 2,
  },
  none: {
    color: 'gray',
    label: 'Keine Angabe',
    learnerHint: 'Noch keine Hinweise. Sie erscheinen, sobald es Daten gibt.',
    coachHint: 'Noch keine Daten',
    rank: 3,
  },
}

export function ampelDisplay(colour: AmpelColour | string): AmpelDisplay {
  return DISPLAY[(colour as AmpelColour) in DISPLAY ? (colour as AmpelColour) : 'none']
}

export const SIGNAL_LABELS: Record<SignalKey, string> = {
  plan: 'Planerfüllung',
  backlog: 'Pfadrückstand',
  reflection: 'Reflexion',
  selfassessment: 'Selbsteinschätzung',
}

/** Very short labels for compact badges in the Lerndoku table. */
export const SIGNAL_SHORT: Record<SignalKey, string> = {
  plan: 'Plan',
  backlog: 'Rückstand',
  reflection: 'Reflexion',
  selfassessment: 'Selbst',
}

export function signalLabel(key: string): string {
  return SIGNAL_LABELS[key as SignalKey] ?? key
}

/** Colour of a signal for display; inactive signals are always neutral. */
export function signalColour(signal: Signal): AmpelColour {
  return signal.active ? signal.colour : 'none'
}

/** Sorts by Ampel (red first), then by name. */
export function compareAmpel(a: AmpelColour, b: AmpelColour): number {
  return ampelDisplay(a).rank - ampelDisplay(b).rank
}

export const SELF_ASSESSMENT_LEVELS: { value: number; label: string; colour: AmpelColour }[] = [
  { value: 1, label: 'sicher', colour: 'green' },
  { value: 2, label: 'geht so', colour: 'green' },
  { value: 3, label: 'unsicher', colour: 'yellow' },
  { value: 4, label: 'komme nicht klar', colour: 'red' },
]

export function selfAssessmentLabel(level: number): string {
  return SELF_ASSESSMENT_LEVELS.find((l) => l.value === level)?.label ?? 'nicht angegeben'
}

export const LEVEL_LABELS: Record<number, string> = {
  0: 'keine Angabe',
  1: 'Basis',
  2: 'Standard',
  3: 'Experte',
}

/**
 * Lerndoku order: by Ampel (red first, then by name) or by name only.
 *
 * "By name" keeps the order of `list`: the plugin sends the learners sorted by last name, then first
 * name. `fullname` starts with the first name, so sorting by it would sort by first name.
 */
export function sortLearners(list: LerndokuLearner[], by: 'ampel' | 'name'): LerndokuLearner[] {
  const position = new Map(list.map((l, i) => [l.userid, i]))
  const byName = (a: LerndokuLearner, b: LerndokuLearner) => position.get(a.userid)! - position.get(b.userid)!
  return [...list].sort((a, b) =>
    by === 'ampel' ? compareAmpel(a.ampel.colour, b.ampel.colour) || byName(a, b) : byName(a, b),
  )
}
