import ListDetailContent from "./ListDetailContent";

export const metadata = {
  title: "List - MangaDex",
};

export default async function ListDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ListDetailContent id={id} />;
}
