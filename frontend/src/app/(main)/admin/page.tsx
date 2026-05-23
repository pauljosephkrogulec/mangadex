import type { Metadata } from "next";
import AdminContent from "./AdminContent";

export const metadata: Metadata = {
  title: "Admin Panel — MangaDex",
};

export default function AdminPage() {
  return <AdminContent />;
}
