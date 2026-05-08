import Topbar from "@/components/Topbar";
import Footer from "@/components/Footer";
import MangaGrid from "@/components/MangaGrid";

export default function HomePage() {
  return (
    <div className="flex flex-col">
      <Topbar />
      <MangaGrid />
      <Footer />
    </div>
  );
}
