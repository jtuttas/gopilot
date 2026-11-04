import type { Meta } from '../api/types'
import { LEVEL_LABELS } from './ampel'

const MOD_LABELS: Record<string, string> = {
  page: 'Seite',
  assign: 'Aufgabe',
  quiz: 'Test',
  url: 'Link',
  forum: 'Forum',
  h5pactivity: 'H5P',
  lesson: 'Lektion',
  resource: 'Datei',
  book: 'Buch',
}

/** German name of a Moodle module type. */
export function modLabel(modname: string): string {
  return MOD_LABELS[modname] ?? modname
}

/** Minutes -> '45 min', '2 h', '1 h 30 min'. */
export function formatDuration(min: number): string {
  if (!min) return ''
  if (min < 60) return `${min} min`
  const h = Math.floor(min / 60)
  const m = min % 60
  return m ? `${h} h ${m} min` : `${h} h`
}

/** Pfad-Metadaten as short text: 'Durchführen · 2 h · Niveau Standard'. */
export function metaText(meta: Meta): string {
  const parts: string[] = []
  if (meta.phase) parts.push(meta.phase)
  if (meta.duration) parts.push(formatDuration(meta.duration))
  if (meta.level) parts.push(`Niveau ${LEVEL_LABELS[meta.level] ?? meta.level}`)
  return parts.join(' · ')
}
