import { Box, Group, Paper, Text } from '@mantine/core'
import type { ReactNode } from 'react'

interface NoticeProps {
  color?: string
  icon?: ReactNode
  title?: ReactNode
  children?: ReactNode
  p?: string
}

/**
 * Informational box that looks like an Alert but is not announced as one
 * (Mantine's Alert always has role="alert"; keep that for errors).
 */
export function Notice({ color = 'blue', icon, title, children, p = 'sm' }: NoticeProps) {
  return (
    <Paper p={p} radius="md" bg={`var(--mantine-color-${color}-light)`} role="note">
      <Group gap="sm" wrap="nowrap" align="flex-start">
        {icon && (
          <Box c={`var(--mantine-color-${color}-light-color)`} style={{ display: 'flex', flexShrink: 0 }} aria-hidden>
            {icon}
          </Box>
        )}
        <Box style={{ flex: 1, minWidth: 0 }}>
          {title && (
            <Text fw={700} size="sm" c={`var(--mantine-color-${color}-light-color)`} mb={children ? 4 : 0}>
              {title}
            </Text>
          )}
          {typeof children === 'string' ? <Text size="sm">{children}</Text> : children}
        </Box>
      </Group>
    </Paper>
  )
}
