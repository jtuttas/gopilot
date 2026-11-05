import type { ReactNode } from 'react'
import { Group, Text, ThemeIcon } from '@mantine/core'
import { IconBook2 } from '@tabler/icons-react'

interface CourseHeadingProps {
  name: string
  /** Mantine colour from `courseColours`. */
  color: string
  size?: 'sm' | 'md'
  /** Shown at the right end, e.g. a counter. */
  right?: ReactNode
  /** Heading element for the name. */
  as?: 'h3' | 'h4' | 'p'
}

/** Course name with its accent colour, used as a group header for Lernschritte. */
export function CourseHeading({ name, color, size = 'md', right, as = 'h4' }: CourseHeadingProps) {
  return (
    <Group justify="space-between" wrap="nowrap" gap="xs">
      <Group gap="xs" wrap="nowrap" style={{ minWidth: 0 }}>
        <ThemeIcon color={color} variant="light" size={size === 'md' ? 28 : 22} radius="md" aria-hidden>
          <IconBook2 size={size === 'md' ? 18 : 14} />
        </ThemeIcon>
        <Text component={as} m={0} fw={700} size={size} style={{ minWidth: 0 }}>
          {name}
        </Text>
      </Group>
      {right}
    </Group>
  )
}
