import { NavLink, Stack } from '@mantine/core'
import { IconChevronRight, IconLogout } from '@tabler/icons-react'
import { Link } from 'react-router'
import { useOverview } from '../api/hooks'
import { useAuth } from '../auth/useAuth'
import { PageHeader } from '../components/PageHeader'
import { moreItems, rolesOf } from '../layout/nav'

/** Mobile "Mehr" menu with the entries that do not fit the bottom bar. */
export function MorePage() {
  const overview = useOverview()
  const { logout } = useAuth()
  const items = moreItems(rolesOf(overview.data?.courses))
  return (
    <>
      <PageHeader title="Mehr" />
      <Stack gap={4}>
        {items.map((item) => (
          <NavLink
            key={item.to}
            component={Link}
            to={item.to}
            label={item.label}
            leftSection={<item.icon size={20} aria-hidden />}
            rightSection={<IconChevronRight size={16} aria-hidden />}
            style={{ borderRadius: 'var(--mantine-radius-md)' }}
          />
        ))}
        <NavLink
          component="button"
          label="Abmelden"
          c="red"
          leftSection={<IconLogout size={20} aria-hidden />}
          onClick={() => logout()}
          style={{ borderRadius: 'var(--mantine-radius-md)' }}
        />
      </Stack>
    </>
  )
}
