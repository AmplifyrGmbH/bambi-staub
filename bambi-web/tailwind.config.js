/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        linen: '#FBF9F4',
        'linen-dark': '#F0EDE4',
        forest: '#2D3E33',
        'forest-light': '#3D5244',
        'forest-muted': '#5A7263',
        charcoal: '#2C2C2C',
        'charcoal-light': '#4A4A4A',
        stone: '#8C7B6B',
        'stone-light': '#BFB5A8',
        terracotta: '#A05C3B',
        'terracotta-light': '#C47A55',
        cream: '#EDE8DF',
      },
      fontFamily: {
        serif: ['"Noto Serif"', 'Georgia', 'serif'],
        sans: ['"Inter"', 'system-ui', 'sans-serif'],
      },
      letterSpacing: {
        widest: '0.2em',
        wider: '0.12em',
      },
    },
  },
  plugins: [],
}
