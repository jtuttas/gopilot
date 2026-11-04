import {
  AppShell,
  Box,
  Container,
  Divider,
  Group,
  NavLink as MantineNavLink,
  ScrollArea,
  Text,
  UnstyledButton,
} from '@mantine/core'
import { useMediaQuery } from '@mantine/hooks'
import { NavLink, Outlet, useLocation } from 'react-router'
import { useOverview } from '../api/hooks'
import { Logo } from '../components/Logo'
import { bottomItems, rolesOf, sidebarItems, type NavItem } from './nav'
import classes from './AppLayout.module.css'

function isActive(pathname: string, item: NavItem): boolean {
  if (item.end) return pathname === item.to
  return pathname === item.to || pathname.startsWith(`${item.to}/`)
}

function SidebarSection({ title, items, pathname }: { title?: string; items: NavItem[]; pathname: string }) {
  if (items.length === 0) return null
  return (
    <Box mb="sm">
      {title && (
        <Text size="xs" fw={700} c="dimmed" tt="uppercase" px="sm" mb={4}>
          {title}
        </Text>
      )}
      {items.map((item) => (
        <MantineNavLink
          key={item.to}
          component={NavLink}
          to={item.to}
          end={item.end}
          label={item.label}
          leftSection={<item.icon size={18} aria-hidden />}
          active={isActive(pathname, item)}
          style={{ borderRadius: 'var(--mantine-radius-md)' }}
        />
      ))}
    </Box>
  )
}

export function AppLayout() {
  const { pathname } = useLocation()
  const overview = useOverview()
  const isMobile = useMediaQuery('(max-width: 47.99em)', false, { getInitialValueInEffect: false })
  const roles = rolesOf(overview.data?.courses)
  const side = sidebarItems(roles)
  const bottom = bottomItems(roles)
  const wide = pathname.startsWith('/lerndoku') || pathname.startsWith('/kurseinstellungen')

  return (
    <AppShell
      header={{ height: 56 }}
      navbar={{ width: 240, breakpoint: 'sm', collapsed: { mobile: true } }}
      footer={isMobile ? { height: 64 } : undefined}
      padding="md"
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between" wrap="nowrap">
          <UnstyledButton component={NavLink} to="/" className={classes.brand} aria-label="GoPilot – zur Startseite">
            <Group gap="xs" wrap="nowrap">
              <Logo size={30} />
              <Text fw={700} size="lg">
                GoPilot
              </Text>
            </Group>
          </UnstyledButton>
          {overview.data && (
            <Text size="sm" c="dimmed" truncate visibleFrom="xs">
              {overview.data.fullname}
            </Text>
          )}
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="xs" aria-label="Hauptnavigation">
        <ScrollArea>
          <SidebarSection title={roles.isCoach || roles.isConfigurer ? 'Lernen' : undefined} items={side.learner} pathname={pathname} />
          <SidebarSection title="Lerncoach" items={side.coach} pathname={pathname} />
          <Divider my="xs" />
          <SidebarSection items={side.general} pathname={pathname} />
        </ScrollArea>
      </AppShell.Navbar>

      <AppShell.Main className={classes.main}>
        <Container size={wide ? 'xl' : 'md'} px={0}>
          <Outlet />
        </Container>
      </AppShell.Main>

      {isMobile && (
        <AppShell.Footer className={classes.footer}>
          <nav aria-label="Hauptnavigation" className={classes.bottomNav}>
            {bottom.map((item) => {
              const active = isActive(pathname, item)
              return (
                <UnstyledButton
                  key={item.to}
                  component={NavLink}
                  to={item.to}
                  end={item.end}
                  className={classes.bottomItem}
                  data-active={active || undefined}
                >
                  <item.icon size={22} aria-hidden />
                  <span>{item.label}</span>
                </UnstyledButton>
              )
            })}
          </nav>
        </AppShell.Footer>
      )}
    </AppShell>
  )
}
