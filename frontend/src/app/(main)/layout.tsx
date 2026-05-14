import LayoutClient from "../LayoutClient";

export default function MainLayout({ children }: { children: React.ReactNode }) {
  return <LayoutClient>{children}</LayoutClient>;
}
