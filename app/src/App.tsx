import { moodleUrl } from './config'

function App() {
  return (
    <main>
      <h1>GoPilot</h1>
      <p>Selbstgesteuert lernen mit Moodle.</p>
      <p className="hint">Moodle: {moodleUrl || 'nicht konfiguriert'}</p>
    </main>
  )
}

export default App
