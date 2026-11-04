import { api } from '../api/functions'

/**
 * Opens a Moodle page in a new tab and logs the user in automatically (ADR 0007).
 *
 * The tab is opened synchronously in the click handler so popup blockers allow it; the one-time login link
 * is loaded into it once the plugin returned it. If that fails (e.g. admin accounts, old plugin version),
 * the page opens without automatic login and Moodle asks for the password as before.
 */
export async function openInMoodle(url: string): Promise<void> {
  const tab = window.open('', '_blank')
  let target = url
  try {
    target = (await api.getMoodleLink(url)).url
  } catch {
    // Fall back to the plain link.
  }
  if (tab) {
    tab.opener = null
    tab.location.href = target
  } else {
    window.location.href = target
  }
}
