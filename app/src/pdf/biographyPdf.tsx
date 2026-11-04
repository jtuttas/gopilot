/* eslint-disable react-refresh/only-export-components -- not a hot-reloaded UI module */
// Lernbiografie PDF, generated in the browser. Loaded lazily (large library).
import { Document, Page, StyleSheet, Text, View, pdf } from '@react-pdf/renderer'
import type { Week } from '../api/types'
import { selfAssessmentLabel } from '../lib/ampel'
import { formatDate, formatDateTime, weekTitle } from '../lib/week'

const styles = StyleSheet.create({
  page: { padding: 40, fontSize: 10, fontFamily: 'Helvetica', lineHeight: 1.4, color: '#1f2933' },
  title: { fontSize: 18, fontFamily: 'Helvetica-Bold', lineHeight: 1.2, marginBottom: 4 },
  subtitle: { fontSize: 9, color: '#616e7c', marginBottom: 16 },
  week: { marginBottom: 14, paddingBottom: 10, borderBottom: '1pt solid #d9e2ec' },
  weekTitle: { fontSize: 13, fontFamily: 'Helvetica-Bold', marginBottom: 6, color: '#1864ab' },
  label: { fontSize: 8, color: '#616e7c', marginTop: 4 },
  text: { fontSize: 10 },
  course: { marginTop: 6, paddingLeft: 8, borderLeft: '2pt solid #a5d8ff' },
  courseTitle: { fontSize: 10, fontFamily: 'Helvetica-Bold', marginBottom: 2 },
  feedback: { marginTop: 2, fontSize: 9 },
  footer: { position: 'absolute', bottom: 20, left: 40, right: 40, fontSize: 8, color: '#9aa5b1', textAlign: 'center' },
})

function WeekBlock({ week }: { week: Week }) {
  const done = week.items.filter((s) => s.completed).length
  return (
    <View style={styles.week}>
      <Text style={styles.weekTitle}>{weekTitle(week.week)}</Text>
      <Text style={styles.label}>Wochenziel</Text>
      <Text style={styles.text}>{week.goal || '–'}</Text>
      {week.items.length > 0 && (
        <>
          <Text style={styles.label}>
            Wochenplan ({done} von {week.items.length} erledigt)
          </Text>
          {week.items.map((s) => (
            <Text key={s.cmid} style={styles.text}>
              {s.completed ? '[x] ' : '[ ] '}
              {s.name}
            </Text>
          ))}
        </>
      )}
      {week.generalreflection !== '' && (
        <>
          <Text style={styles.label}>Allgemeine Reflexion</Text>
          <Text style={styles.text}>{week.generalreflection}</Text>
        </>
      )}
      {week.courses
        .filter((c) => c.reflection || c.selfassessments.some((s) => s.level > 0) || c.feedback.length > 0)
        .map((c) => (
          <View key={c.courseid} style={styles.course} wrap={false}>
            <Text style={styles.courseTitle}>{c.fullname}</Text>
            {c.reflection !== '' && (
              <>
                <Text style={styles.label}>Kursreflexion</Text>
                <Text style={styles.text}>{c.reflection}</Text>
              </>
            )}
            {c.selfassessments
              .filter((s) => s.level > 0)
              .map((s) => (
                <Text key={s.sectionid} style={styles.text}>
                  Selbsteinschätzung {s.name}: {selfAssessmentLabel(s.level)}
                  {s.comment ? ` – ${s.comment}` : ''}
                </Text>
              ))}
            {c.feedback.length > 0 && <Text style={styles.label}>Coaching-Feedback</Text>}
            {c.feedback.map((f) => (
              <Text key={f.id} style={styles.feedback}>
                {f.authorname} ({formatDateTime(f.timecreated)}): {f.message}
              </Text>
            ))}
          </View>
        ))}
    </View>
  )
}

function BiographyDocument({ fullname, weeks }: { fullname: string; weeks: Week[] }) {
  const created = formatDate(Math.floor(Date.now() / 1000))
  return (
    <Document title={`Lernbiografie ${fullname}`} author={fullname} creator="GoPilot" language="de">
      <Page size="A4" style={styles.page}>
        <Text style={styles.title}>Lernbiografie</Text>
        <Text style={styles.subtitle}>
          {fullname} · erstellt am {created} mit GoPilot
        </Text>
        {weeks.map((w) => (
          <WeekBlock key={w.week} week={w} />
        ))}
        <Text
          style={styles.footer}
          render={({ pageNumber, totalPages }) => `Seite ${pageNumber} von ${totalPages}`}
          fixed
        />
      </Page>
    </Document>
  )
}

export async function downloadBiographyPdf(opts: { fullname: string; weeks: Week[]; filename: string }) {
  const blob = await pdf(<BiographyDocument fullname={opts.fullname} weeks={opts.weeks} />).toBlob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = opts.filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}
