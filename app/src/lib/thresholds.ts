import type { Thresholds } from '../api/types'

export type ThresholdErrors = Partial<Record<keyof Thresholds, string>>

/** Mirrors the plugin check (`invalidthresholds`) plus basic ranges. */
export function validateThresholds(t: Thresholds): ThresholdErrors {
  const errors: ThresholdErrors = {}
  for (const [key, value] of Object.entries(t) as [keyof Thresholds, number][]) {
    if (!Number.isInteger(value) || value < 0) errors[key] = 'Bitte eine ganze Zahl ab 0 eingeben.'
  }
  for (const key of ['plangreen', 'planyellow'] as const) {
    if (!errors[key] && t[key] > 100) errors[key] = 'Höchstens 100 %.'
  }
  if (!errors.planyellow && !errors.plangreen && t.planyellow >= t.plangreen) {
    errors.planyellow = 'Gelb muss unter dem Wert für Grün liegen.'
  }
  if (!errors.backlogyellow && !errors.backlogred && t.backlogyellow >= t.backlogred) {
    errors.backlogyellow = 'Gelb muss unter dem Wert für Rot liegen.'
  }
  if (!errors.reflectionyellow && !errors.reflectionred && t.reflectionyellow >= t.reflectionred) {
    errors.reflectionyellow = 'Gelb muss unter dem Wert für Rot liegen.'
  }
  return errors
}
