/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  safelist: [
    // Risk level colors - must always be included (used dynamically)
    'bg-green-50', 'bg-green-100', 'bg-green-600', 'text-green-700', 'text-green-800', 'border-green-200', 'ring-green-400',
    'bg-teal-50', 'bg-teal-100', 'bg-teal-600', 'text-teal-700', 'text-teal-800', 'border-teal-200', 'ring-teal-400',
    'bg-blue-50', 'bg-blue-100', 'bg-blue-600', 'text-blue-700', 'text-blue-800', 'border-blue-200', 'ring-blue-400',
    'bg-red-50', 'bg-red-100', 'bg-red-600', 'text-red-700', 'text-red-800', 'border-red-200', 'ring-red-400',
    // New palette dynamic classes
    'bg-raspberry-50', 'bg-raspberry-100', 'bg-raspberry-500', 'text-raspberry-500', 'text-raspberry-700', 'border-raspberry-300',
    'bg-spring-50', 'bg-spring-100', 'bg-spring-500', 'text-spring-500', 'text-spring-700', 'border-spring-300',
    'bg-violet-50', 'bg-violet-100', 'bg-violet-500', 'text-violet-500', 'text-violet-700', 'border-violet-300',
    'bg-horizon-50', 'bg-horizon-100', 'bg-horizon-400', 'text-horizon-500', 'border-horizon-200',
    // Progress bar gradients
    'from-horizon-400', 'to-horizon-500', 'from-horizon-200', 'to-horizon-400',
    'from-raspberry-400', 'to-raspberry-600', 'from-violet-400', 'to-violet-600',
    'bg-savannah-100', 'bg-eggshell-500',
    // Life stage sidebar — dynamic stage colours (progress ring, active states, badges)
    'stroke-violet-500', 'stroke-spring-500', 'stroke-raspberry-500', 'stroke-light-blue-500', 'stroke-horizon-500',
    'bg-light-blue-100', 'bg-light-blue-500', 'text-light-blue-500', 'hover:bg-light-blue-600',
    'bg-light-pink-100', 'hover:bg-light-pink-400', 'bg-light-pink-50',
    'bg-light-pink-200', 'border-light-pink-200', 'border-light-pink-300', 'hover:bg-light-pink-100',
    'bg-horizon-500', 'text-horizon-700', 'stroke-light-gray',
  ],
  theme: {
    extend: {
      colors: {
        // === FYNLA DESIGN SYSTEM v1.2.0 ===
        raspberry: {
          50: '#FDF2F8',
          100: '#FCE7F3',
          200: '#F9A8D4',
          300: '#F472B6',
          400: '#EC4899',
          500: '#E83E6D',
          600: '#DB2777',
          700: '#BE185D',
          800: '#9D174D',
          900: '#831843',
        },
        horizon: {
          50: '#F8FAFC',
          100: '#F1F5F9',
          200: '#E2E8F0',
          300: '#CBD5E1',
          400: '#94A3B8',
          500: '#1F2A44',
          600: '#0F172A',
          700: '#020617',
          800: '#0A0E1A',
          900: '#03060D',
        },
        spring: {
          50: '#F0FDF9',
          100: '#D1FAE5',
          200: '#A7F3D0',
          300: '#6EE7B7',
          400: '#34D399',
          500: '#20B486',
          600: '#059669',
          700: '#047857',
          800: '#065F46',
          900: '#064E3B',
        },
        violet: {
          50: '#F5F3FF',
          100: '#EDE9FE',
          200: '#DDD6FE',
          300: '#C4B5FD',
          400: '#A78BFA',
          500: '#5854E6',
          600: '#7C3AED',
          700: '#6D28D9',
          800: '#581C87',
          900: '#4C1D5F',
        },
        savannah: {
          50: '#FEFCFB',
          100: '#FDFAF7',
          200: '#FAF5F0',
          300: '#F5EDE5',
          400: '#EFDCD1',
          500: '#E6C9A8',
          600: '#D1B08C',
          700: '#A88E6E',
          800: '#8A7359',
          900: '#6B5845',
        },
        eggshell: {
          50: '#FFFFFF',
          100: '#FEFEFE',
          500: '#F7F6F4',
          900: '#E7E5E2',
        },
        neutral: {
          500: '#717171',
        },
        'light-gray': '#EEEEEE',
        'light-blue': {
          100: '#DDE2EF',
          500: '#6C83BC',
        },
        'light-pink': {
          50: '#FDF0F4',
          100: '#FAD6E0',
          200: '#F5B3C5',
          300: '#F095AD',
          400: '#EF7598',
        },

        // === SEMANTIC COLORS (updated to new palette) ===
        success: {
          100: '#D1FAE5',
          500: '#20B486',
          600: '#059669',
          700: '#047857',
        },

        // === CHART COLORS (updated to new palette) ===
        chart: {
          1: '#1F2A44',     // Horizon 500
          2: '#20B486',     // Spring 500
          3: '#5854E6',     // Violet 500
          4: '#E83E6D',     // Raspberry 500
          5: '#E6C9A8',     // Savannah 500
          6: '#6C83BC',     // Light Blue 500
          7: '#717171',     // Neutral 500
          8: '#0F172A',     // Horizon 600
        },
      },
      fontFamily: {
        sans: ['Segoe UI', 'Inter', '-apple-system', 'BlinkMacSystemFont', 'Roboto', 'sans-serif'],
        display: ['Segoe UI', 'Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'Courier New', 'monospace'],
      },
      fontSize: {
        'display': ['3.75rem', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '900' }],
        'h1': ['2.25rem', { lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '900' }],
        'h2': ['1.875rem', { lineHeight: '1.3', fontWeight: '700' }],
        'h3': ['1.5rem', { lineHeight: '1.4', fontWeight: '700' }],
        'h4': ['1.25rem', { lineHeight: '1.5', fontWeight: '700' }],
        'h5': ['1rem', { lineHeight: '1.5', fontWeight: '700' }],
        'body-lg': ['1.125rem', { lineHeight: '1.7', fontWeight: '400' }],
        'body': ['1rem', { lineHeight: '1.6', fontWeight: '400' }],
        'body-sm': ['0.875rem', { lineHeight: '1.5', fontWeight: '400' }],
        'caption': ['0.75rem', { lineHeight: '1.4', fontWeight: '400' }],
      },
      spacing: {
        '128': '32rem',
        '144': '36rem',
      },
      borderRadius: {
        'card': '0.75rem',
        'button': '0.5rem',
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
        'card-hover': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        'modal': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
      },
    },
  },
  plugins: [],
}
