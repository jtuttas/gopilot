import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router'
import { AppProviders } from './AppProviders'
import { useAuth } from './auth/useAuth'
import { Loading } from './components/QueryState'
import { AppLayout } from './layout/AppLayout'
import { LoginPage } from './views/LoginPage'
import { MorePage } from './views/MorePage'
import { CourseListPage, CoursePathsPage } from './views/PathsPage'
import { PlanPage } from './views/PlanPage'
import { ReflectionPage } from './views/ReflectionPage'
import { SettingsPage } from './views/SettingsPage'
import { StartPage } from './views/StartPage'

// Less frequently used views are loaded on demand.
const BiographyPage = lazy(() => import('./views/BiographyPage').then((m) => ({ default: m.BiographyPage })))
const LerndokuIndexPage = lazy(() => import('./views/LerndokuPage').then((m) => ({ default: m.LerndokuIndexPage })))
const LerndokuPage = lazy(() => import('./views/LerndokuPage').then((m) => ({ default: m.LerndokuPage })))
const LearnerPage = lazy(() => import('./views/LearnerPage').then((m) => ({ default: m.LearnerPage })))
const CourseSettingsPage = lazy(() =>
  import('./views/CourseSettingsPage').then((m) => ({ default: m.CourseSettingsPage })),
)

/** Push links use `/woche/<Montag>` (api-v1.md); they open the week's reflection. */
function WeekRedirect() {
  const { week } = useParams()
  return <Navigate to={`/reflexion/${week ?? ''}`} replace />
}

export function AppRoutes() {
  const { token } = useAuth()
  if (!token) return <LoginPage />
  return (
    <Suspense fallback={<Loading />}>
      <Routes>
        <Route element={<AppLayout />}>
          <Route index element={<StartPage />} />
          <Route path="lernpfade" element={<CourseListPage />} />
          <Route path="lernpfade/:courseid" element={<CoursePathsPage />} />
          <Route path="plan/:week?" element={<PlanPage />} />
          <Route path="reflexion/:week?" element={<ReflectionPage />} />
          <Route path="woche/:week" element={<WeekRedirect />} />
          <Route path="lernbiografie" element={<BiographyPage />} />
          <Route path="lerndoku" element={<LerndokuIndexPage />} />
          <Route path="lerndoku/:courseid" element={<LerndokuPage />} />
          <Route path="lerndoku/:courseid/:userid" element={<LearnerPage />} />
          <Route path="kurseinstellungen/:courseid?" element={<CourseSettingsPage />} />
          <Route path="einstellungen" element={<SettingsPage />} />
          <Route path="mehr" element={<MorePage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </Suspense>
  )
}

function App() {
  return (
    // BASE_URL is '/' locally and e.g. '/gopilot/' on GitHub Pages (vite build --base).
    <BrowserRouter basename={import.meta.env.BASE_URL.replace(/\/$/, '') || '/'}>
      <AppProviders>
        <AppRoutes />
      </AppProviders>
    </BrowserRouter>
  )
}

export default App
