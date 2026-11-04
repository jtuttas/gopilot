import { createTheme } from '@mantine/core'

const fontFamily = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'

export const theme = createTheme({
  primaryColor: 'blue',
  defaultRadius: 'md',
  fontFamily,
  headings: { fontFamily },
})
