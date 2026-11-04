import { describe, expect, it } from 'vitest'
import {
  addWeeks,
  isMonday,
  isoWeek,
  mondayOf,
  relativeWeekLabel,
  weekRangeLabel,
  weekTitle,
} from './week'

describe('Wochen-Helfer', () => {
  it('findet den Montag einer Woche', () => {
    expect(mondayOf(new Date(2026, 10, 3))).toBe('2026-11-02') // Dienstag
    expect(mondayOf(new Date(2026, 10, 2))).toBe('2026-11-02') // Montag selbst
    expect(mondayOf(new Date(2026, 10, 8, 23, 59))).toBe('2026-11-02') // Sonntagabend
    expect(mondayOf(new Date(2026, 10, 9, 0, 0))).toBe('2026-11-09')
  })

  it('findet den Montag über den Jahreswechsel', () => {
    expect(mondayOf(new Date(2027, 0, 1))).toBe('2026-12-28')
    expect(mondayOf(new Date(2026, 0, 1))).toBe('2025-12-29')
  })

  it('bleibt über die Zeitumstellung stabil', () => {
    expect(addWeeks('2026-10-19', 1)).toBe('2026-10-26')
    expect(addWeeks('2026-03-23', 1)).toBe('2026-03-30')
    expect(mondayOf(new Date(2026, 9, 25, 12))).toBe('2026-10-19')
  })

  it('berechnet die ISO-Kalenderwoche', () => {
    expect(isoWeek('2026-11-02')).toEqual({ week: 45, year: 2026 })
    expect(isoWeek('2026-12-28')).toEqual({ week: 53, year: 2026 })
    expect(isoWeek('2027-01-04')).toEqual({ week: 1, year: 2027 })
    expect(isoWeek('2024-12-30')).toEqual({ week: 1, year: 2025 })
    expect(isoWeek('2021-01-03')).toEqual({ week: 53, year: 2020 })
  })

  it('rechnet Wochen vor und zurück über das Jahresende', () => {
    expect(addWeeks('2026-12-28', 1)).toBe('2027-01-04')
    expect(addWeeks('2027-01-04', -1)).toBe('2026-12-28')
  })

  it('prüft Montage', () => {
    expect(isMonday('2026-11-02')).toBe(true)
    expect(isMonday('2026-11-03')).toBe(false)
    expect(isMonday('2026-02-30')).toBe(false)
    expect(isMonday('kaputt')).toBe(false)
  })

  it('formatiert Wochen deutsch', () => {
    expect(weekRangeLabel('2026-11-02')).toBe('02.11.–08.11.2026')
    expect(weekTitle('2026-12-28')).toBe('KW 53 · 28.12.–03.01.2027')
    expect(relativeWeekLabel('2026-11-09', '2026-11-02')).toBe('Nächste Woche')
    expect(relativeWeekLabel('2026-10-26', '2026-11-02')).toBe('Letzte Woche')
  })
})
