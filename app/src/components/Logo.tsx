/** GoPilot mark: a path with a paper plane (same artwork as the app icon). */
export function Logo({ size = 32 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 512 512" aria-hidden="true" focusable="false">
      <rect width="512" height="512" rx="112" fill="#1c7ed6" />
      <path
        d="M112 380c60-10 92-46 120-92"
        fill="none"
        stroke="#a5d8ff"
        strokeWidth="26"
        strokeLinecap="round"
        strokeDasharray="2 48"
      />
      <path d="M404 112 120 236l108 38 38 108z" fill="#fff" />
      <path d="M404 112 228 274l38 108z" fill="#d0ebff" />
    </svg>
  )
}
