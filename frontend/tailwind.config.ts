import type { Config } from "tailwindcss";

const config: Config = {
  theme: {
    extend: {
      colors: {
        mangadex: {
          orange: '#ff6740',
          dark: '#0F1114',
          'dark-light': '#181A1E',
          'gray-dark': '#1a1d23',
        },
      },
      maxWidth: {
        'content': '1280px',
      },
    },
  },
  plugins: [],
};
export default config;
