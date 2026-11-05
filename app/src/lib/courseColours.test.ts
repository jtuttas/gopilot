import { describe, expect, it } from 'vitest'
import { courseColours, shortCourseName } from './courseColours'

describe('shortCourseName', () => {
  it('uses a leading Lernfeld code', () => {
    expect(shortCourseName('LF5 - Software zur Verwaltung von Daten anpassen Kopie 1')).toBe('LF5')
    expect(shortCourseName('LF8 Daten systemübergreifend bereitstellen')).toBe('LF8')
  })

  it('cuts long names without a code', () => {
    expect(shortCourseName('Mathematik')).toBe('Mathematik')
    expect(shortCourseName('Wirtschafts- und Sozialkunde')).toBe('Wirtschafts- und …')
  })
})

describe('courseColours', () => {
  it('gives each course its own colour, without Ampel colours', () => {
    const colours = courseColours([{ id: 5 }, { id: 8 }, { id: 2 }])
    expect(colours.get(5)).toBe('blue')
    expect(colours.get(8)).toBe('grape')
    expect(new Set(colours.values()).size).toBe(3)
    for (const c of colours.values()) expect(['red', 'yellow', 'teal']).not.toContain(c)
  })

  it('repeats the palette for many courses', () => {
    const colours = courseColours(Array.from({ length: 9 }, (_, i) => ({ id: i + 1 })))
    expect(colours.get(9)).toBe(colours.get(1))
  })
})
