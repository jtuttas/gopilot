import { describe, expect, it } from 'vitest'
import type { LerndokuLearner, Signal } from '../api/types'
import {
  ampelDisplay,
  selfAssessmentLabel,
  signalColour,
  signalLabel,
  sortLearners,
} from './ampel'
import { validateThresholds } from './thresholds'

const learner = (fullname: string, colour: LerndokuLearner['ampel']['colour']): LerndokuLearner => ({
  userid: fullname.length,
  fullname,
  ampel: { courseid: 1, colour, timecomputed: 0, signals: [] },
  progress: { completed: 0, total: 0 },
  lastreflection: { week: '', text: '' },
  unreadfeedback: 0,
  cohorts: [],
})

describe('Ampel-Darstellung', () => {
  it('ordnet jeder Farbe Mantine-Farbe und Wort zu', () => {
    expect(ampelDisplay('green')).toMatchObject({ color: 'teal', label: 'Grün' })
    expect(ampelDisplay('yellow')).toMatchObject({ color: 'yellow', label: 'Gelb' })
    expect(ampelDisplay('red')).toMatchObject({ color: 'red', label: 'Rot' })
    expect(ampelDisplay('none')).toMatchObject({ color: 'gray', label: 'Keine Angabe' })
    expect(ampelDisplay('unbekannt')).toEqual(ampelDisplay('none'))
  })

  it('formuliert unterstützend, ohne Bewertung', () => {
    for (const c of ['green', 'yellow', 'red', 'none'] as const) {
      expect(ampelDisplay(c).learnerHint).not.toMatch(/schlecht|Note|Rang|ungenügend/i)
    }
  })

  it('zeigt inaktive Signale neutral', () => {
    const s: Signal = { key: 'plan', active: false, colour: 'red', value: 0, reason: 'Kein Lernschritt dieses Kurses geplant' }
    expect(signalColour(s)).toBe('none')
    expect(signalColour({ ...s, active: true })).toBe('red')
    expect(signalLabel('backlog')).toBe('Pfadrückstand')
  })

  it('sortiert Rot zuerst, dann nach Name', () => {
    const list = [learner('Ada', 'green'), learner('Can', 'red'), learner('Ben', 'yellow'), learner('Bea', 'red'), learner('Dora', 'none')]
    expect(sortLearners(list, 'ampel').map((l) => l.fullname)).toEqual(['Bea', 'Can', 'Ben', 'Ada', 'Dora'])
    expect(sortLearners(list, 'name').map((l) => l.fullname)).toEqual(['Ada', 'Bea', 'Ben', 'Can', 'Dora'])
  })

  it('benennt die Stufen der Selbsteinschätzung', () => {
    expect(selfAssessmentLabel(1)).toBe('sicher')
    expect(selfAssessmentLabel(4)).toBe('komme nicht klar')
    expect(selfAssessmentLabel(0)).toBe('nicht angegeben')
  })
})

describe('Schwellwerte', () => {
  const ok = { plangreen: 70, planyellow: 40, backlogyellow: 2, backlogred: 4, reflectionyellow: 1, reflectionred: 2 }
  it('akzeptiert die Standardwerte', () => {
    expect(validateThresholds(ok)).toEqual({})
  })
  it('verlangt Gelb unter Grün bzw. Rot', () => {
    const e = validateThresholds({ ...ok, planyellow: 70, backlogyellow: 4, reflectionyellow: 3 })
    expect(Object.keys(e).sort()).toEqual(['backlogyellow', 'planyellow', 'reflectionyellow'])
  })
  it('begrenzt Prozentwerte', () => {
    expect(validateThresholds({ ...ok, plangreen: 120 })).toHaveProperty('plangreen')
  })
})
