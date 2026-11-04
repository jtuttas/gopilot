/** Basis-URL der Moodle-Instanz, gesetzt über VITE_MOODLE_URL. */
export const moodleUrl: string = (import.meta.env.VITE_MOODLE_URL ?? '').replace(/\/+$/, '')

/** Mock mode (VITE_MOCK=1): answers all calls from in-memory fixtures. */
export function isMockMode(): boolean {
  return import.meta.env.VITE_MOCK === '1'
}
