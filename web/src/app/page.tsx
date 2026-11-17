import { Button } from "@/components/ui/button";

export default function Home() {
  return (
    <main className="flex flex-1 flex-col items-center justify-center gap-6 p-8 text-center">
      <h1 className="text-4xl font-bold tracking-tight">Mangadex</h1>
      <p className="max-w-md text-muted-foreground">
        Lisez vos mangas et webtoons préférés, où que vous soyez.
      </p>
      <Button>Explorer le catalogue</Button>
    </main>
  );
}
