/** A value that can be sent to a Moodle web service. */
export type ParamValue =
  | string
  | number
  | boolean
  | null
  | undefined
  | ParamValue[]
  | { [key: string]: ParamValue }

export type Params = Record<string, ParamValue>

/**
 * Flattens nested params into Moodle notation:
 * `{ cmids: [5, 7] }` -> `cmids[0]=5, cmids[1]=7`,
 * `{ courses: [{ courseid: 2 }] }` -> `courses[0][courseid]=2`.
 * Booleans become `1`/`0`; `null` and `undefined` are skipped.
 */
export function flattenParams(params: Params): [string, string][] {
  const out: [string, string][] = []
  const walk = (prefix: string, value: ParamValue) => {
    if (value === null || value === undefined) return
    if (Array.isArray(value)) {
      value.forEach((v, i) => walk(`${prefix}[${i}]`, v))
    } else if (typeof value === 'object') {
      for (const [k, v] of Object.entries(value)) walk(`${prefix}[${k}]`, v)
    } else if (typeof value === 'boolean') {
      out.push([prefix, value ? '1' : '0'])
    } else {
      out.push([prefix, String(value)])
    }
  }
  for (const [key, value] of Object.entries(params)) walk(key, value)
  return out
}

/** Encodes params as `application/x-www-form-urlencoded` body. */
export function toFormBody(params: Params): URLSearchParams {
  const body = new URLSearchParams()
  for (const [k, v] of flattenParams(params)) body.append(k, v)
  return body
}
