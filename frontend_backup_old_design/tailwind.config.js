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
          primary: '#4A6FA5',
          secondary: '#7B9CC2',
          accent: '#C4A35A',
          light: '#F5F7FA',
          dark: '#2C3E50',
        }
      }
    },
  },
  plugins: [],
}
