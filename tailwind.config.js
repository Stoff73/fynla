/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  safelist: [
    // Risk level colors - ensure these are always included
    'bg-green-50', 'bg-green-100', 'bg-green-600', 'text-green-700', 'text-green-800', 'border-green-200', 'ring-green-400',
    'bg-teal-50', 'bg-teal-100', 'bg-teal-600', 'text-teal-700', 'text-teal-800', 'border-teal-200', 'ring-teal-400',
    'bg-blue-50', 'bg-blue-100', 'bg-blue-600', 'text-blue-700', 'text-blue-800', 'border-blue-200', 'ring-blue-400',
    'bg-amber-50', 'bg-amber-100', 'bg-amber-600', 'text-amber-700', 'text-amber-800', 'border-amber-200', 'ring-amber-400',
    'bg-red-50', 'bg-red-100', 'bg-red-600', 'text-red-700', 'text-red-800', 'border-red-200', 'ring-red-400',
  ],
  theme: {
    extend: {
      colors: {
        // Professional Palette (Deep Navy & Slate)
        primary: {
          50: '#FFFFFF',      // Was Pastel Blue
          100: '#F1F5F9',     // Slate 100
          200: '#E2E8F0',     // Slate 200
          300: '#CBD5E1',     // Slate 300
          400: '#94A3B8',     // Slate 400
          500: '#3B82F6',     // Default Blue (keeping as bright accent)
          600: '#1257A0',     // Trust Blue (Main Brand Color)
          700: '#0E3A66',     // Deep Navy
          800: '#0B2C4F',     // Darker Navy
          900: '#051B33',     // Darkest Navy
          950: '#020617',
        },
        // Secondary (Neutrals/Slate instead of Teal)
        secondary: {
          50: '#FFFFFF',
          100: '#F1F5F9',
          200: '#E2E8F0',
          500: '#64748B',     // Slate 500
          600: '#475569',     // Slate 600
          700: '#334155',     // Slate 700
          800: '#1E293B',     // Slate 800
          900: '#0F172A',     // Slate 900
        },
        // Action/Status Colors (Solid, not Pastel)
        success: {
          50: '#FFFFFF',
          100: '#F0FDF4',     // Very subtle
          500: '#15803D',     // Solid Green
          600: '#166534',
          700: '#14532D',
          800: '#14532D',
          900: '#14532D',
        },
        error: {
          50: '#FFFFFF',
          100: '#FEF2F2',     // Very subtle
          500: '#EF4444',     // Solid Red
          600: '#B91C1C',     // Darker Red
          700: '#991B1B',
          800: '#7F1D1D',
          900: '#450A0A',
        },
        warning: {
          50: '#FFFFFF',
          100: '#FFFBEB',
          500: '#F59E0B',     // Solid Amber
          600: '#D97706',
          700: '#B45309',
          800: '#92400E',
          900: '#78350F',
        },
        info: {
          50: '#FFFFFF',
          100: '#F0F9FF',
          500: '#0EA5E9',
          600: '#0284C7',
          700: '#0369A1',
          800: '#075985',
          900: '#0C4A6E',
        },
        // Chart Colors (Keep diverse but professional)
        chart: {
          1: '#1257A0', // Trust Blue
          2: '#475569', // Slate
          3: '#15803D', // Green
          4: '#D97706', // Amber
          5: '#B91C1C', // Red
          6: '#7C3AED', // Purple (kept only for charts)
          7: '#C2410C', // Orange
          8: '#0F172A', // Navy
        },
      },
      fontFamily: {
        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        display: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'Courier New', 'monospace'],
      },
      fontSize: {
        'display': ['3.75rem', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' }],
        'h1': ['2.25rem', { lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '700' }],
        'h2': ['1.875rem', { lineHeight: '1.3', fontWeight: '600' }],
        'h3': ['1.5rem', { lineHeight: '1.4', fontWeight: '600' }],
        'h4': ['1.25rem', { lineHeight: '1.5', fontWeight: '600' }],
        'h5': ['1rem', { lineHeight: '1.5', fontWeight: '600' }],
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
