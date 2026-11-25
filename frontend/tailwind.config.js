/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        church: {
          primary: '#2b4c8e',
          secondary: '#3a5f9f',
          accent: '#c9a44c',
          gold: '#c9a44c',
          light: '#eef2f9',
          dark: '#1e3766',
          cream: '#f3efe6',
          navy: '#1a2340',
        }
      },
      fontFamily: {
        display: ['Amiri', 'Cairo', 'serif'],
        sans: ['Cairo', 'Segoe UI', 'Tahoma', 'sans-serif'],
      },
    },
  },
  plugins: [],
}