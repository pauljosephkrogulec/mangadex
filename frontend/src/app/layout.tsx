import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Mangadex",
  description: "A manga reader built with Next.js and Tailwind CSS.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
