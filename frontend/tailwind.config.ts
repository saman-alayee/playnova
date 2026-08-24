import type { Config } from 'tailwindcss'

export default {
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './plugins/**/*.{js,ts}',
    './app.vue',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#9333EA',
          light: '#A855F7',
        },
        secondary: '#3B82F6',
        success: '#22C55E',
        danger: '#EF4444',
        'bg-dark': '#050505',
        'dark-600': '#2d2d44',
        'dark-700': '#1a1a2e',
        'dark-800': '#12121c',
        'dark-900': '#0a0a12',
      },
      boxShadow: {
        glowprimary: '0 0 20px rgba(147, 51, 234, 0.3)',
        glowsuccess: '0 0 20px rgba(34, 197, 94, 0.3)',
      },
      maxWidth: {
        '7xl': '80rem',
      },
    },
  },
  plugins: [],
} satisfies Config
