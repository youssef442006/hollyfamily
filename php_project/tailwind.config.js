/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./d:/كنيستي/php_project/**/*.{php,html,js}",
    "./php_project/**/*.{php,html,js}",
    "../php_project/**/*.{php,html,js}"
  ],
  theme: {
    extend: {
      colors: {
        church: {
          navy: '#15264a',
          blue: '#1e3766',
          dark: '#0f1b36',
          gold: '#c9a44c',
          'gold-hover': '#b0872b',
          'gold-light': '#fbf7ee',
          surface: '#ffffff',
          bg: '#f8fafc'
        }
      },
      fontFamily: {
        sans: ['Cairo', 'Segoe UI', 'Tahoma', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
