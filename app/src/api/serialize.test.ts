import { describe, expect, it } from 'vitest'
import { flattenParams, toFormBody } from './serialize'

describe('flattenParams (Moodle-Notation)', () => {
  it('lässt einfache Werte unverändert', () => {
    expect(flattenParams({ courseid: 2, week: '2026-11-02' })).toEqual([
      ['courseid', '2'],
      ['week', '2026-11-02'],
    ])
  })

  it('serialisiert Listen mit Index', () => {
    expect(flattenParams({ cmids: [5, 7] })).toEqual([
      ['cmids[0]', '5'],
      ['cmids[1]', '7'],
    ])
  })

  it('serialisiert verschachtelte Objekte und Listen', () => {
    const flat = flattenParams({
      courses: [
        { courseid: 2, reflection: 'gut', selfassessments: [{ sectionid: 9, level: 3, comment: '' }] },
      ],
    })
    expect(flat).toEqual([
      ['courses[0][courseid]', '2'],
      ['courses[0][reflection]', 'gut'],
      ['courses[0][selfassessments][0][sectionid]', '9'],
      ['courses[0][selfassessments][0][level]', '3'],
      ['courses[0][selfassessments][0][comment]', ''],
    ])
  })

  it('wandelt Booleans in 1/0 und überspringt null/undefined', () => {
    expect(flattenParams({ usedefaults: true, completed: false, x: null, y: undefined })).toEqual([
      ['usedefaults', '1'],
      ['completed', '0'],
    ])
  })

  it('serialisiert Objekte wie thresholds', () => {
    expect(flattenParams({ thresholds: { plangreen: 70, planyellow: 40 } })).toEqual([
      ['thresholds[plangreen]', '70'],
      ['thresholds[planyellow]', '40'],
    ])
  })

  it('leere Liste erzeugt keine Felder', () => {
    expect(flattenParams({ cmids: [] })).toEqual([])
  })

  it('kodiert als application/x-www-form-urlencoded', () => {
    expect(toFormBody({ goal: 'Ä & ß', cmids: [1] }).toString()).toBe(
      'goal=%C3%84+%26+%C3%9F&cmids%5B0%5D=1',
    )
  })
})
