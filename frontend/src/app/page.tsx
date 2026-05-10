import MangaSection from "@/components/MangaSection";
import Footer from "@/components/Footer";

export default function HomePage() {
  return (
    <div className="flex flex-col overflow-x-hidden">
      {/* ── Welcome banner ── */}
      <section className="relative overflow-hidden border-b border-md-border bg-gradient-to-b from-md-surface to-md-background">
        <div className="max-w-content mx-auto px-6 md:px-8 py-10 md:py-14">
          <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
              <h1 className="text-2xl md:text-3xl font-bold text-md-text-primary tracking-tight">
                Welcome to MangaDex
              </h1>
              <p className="text-md-text-secondary mt-1.5 text-sm md:text-base max-w-xl">
                Discover, read and follow the latest manga, manhwa, and comics — translated by the community.
              </p>
            </div>
            <div className="flex items-center gap-6 md:gap-8 shrink-0">
              <div className="text-center">
                <span className="text-2xl font-bold text-md-accent">50</span>
                <p className="text-xs text-md-text-secondary mt-0.5">Manga</p>
              </div>
              <div className="text-center">
                <span className="text-2xl font-bold text-md-accent">30</span>
                <p className="text-xs text-md-text-secondary mt-0.5">Chapters</p>
              </div>
              <div className="text-center">
                <span className="text-2xl font-bold text-md-accent">178</span>
                <p className="text-xs text-md-text-secondary mt-0.5">Creators</p>
              </div>
            </div>
          </div>
        </div>
        {/* Subtle gradient line at bottom */}
        <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-md-accent/20 to-transparent" />
      </section>

      {/* ── Latest Updates ── */}
      <MangaSection
        title="Latest Updates"
        subtitle="Recently updated manga"
        apiParams={{ "order[createdAt]": "desc" }}
        cols={5}
        limit={12}
        variant="scroll"
      />

      {/* ── Popular Now ── */}
      <MangaSection
        title="Popular Now"
        subtitle="Trending titles this week"
        apiParams={{ "order[createdAt]": "desc", page: "2" }}
        cols={5}
        limit={12}
        variant="scroll"
      />

      {/* ── Recently Added ── */}
      <section className="max-w-content mx-auto px-6 md:px-8 py-8">
        <div className="flex items-end justify-between mb-5">
          <div>
            <h2 className="text-xl font-bold text-md-text-primary">Browse by Status</h2>
            <p className="text-sm text-md-text-secondary mt-0.5">
              Filter manga by publication status
            </p>
          </div>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { status: "ongoing", label: "Ongoing", color: "bg-green-500/10 text-green-400 border-green-500/30", count: 20 },
            { status: "completed", label: "Completed", color: "bg-blue-500/10 text-blue-400 border-blue-500/30", count: 24 },
            { status: "hiatus", label: "Hiatus", color: "bg-yellow-500/10 text-yellow-400 border-yellow-500/30", count: 5 },
            { status: "cancelled", label: "Cancelled", color: "bg-red-500/10 text-red-400 border-red-500/30", count: 1 },
          ].map((item) => (
            <a
              key={item.status}
              href={`/search?status=${item.status}`}
              className="group flex flex-col items-center justify-center p-6 rounded-xl bg-md-surface border border-md-border hover:border-md-accent/30 transition-all duration-200 hover:shadow-lg hover:shadow-md-accent/5"
            >
              <span className="text-2xl font-bold text-md-text-primary group-hover:text-md-accent transition-colors">
                {item.count}
              </span>
              <span className={`mt-1.5 text-xs font-medium px-2.5 py-0.5 rounded-full border ${item.color}`}>
                {item.label}
              </span>
            </a>
          ))}
        </div>
      </section>

      {/* ── Recently Added ── */}
      <MangaSection
        title="Recently Added"
        subtitle="New manga on the platform"
        apiParams={{ "order[createdAt]": "desc", page: "3" }}
        cols={5}
        limit={12}
        variant="scroll"
      />

      {/* ── Browse by Demographic ── */}
      <section className="max-w-content mx-auto px-6 md:px-8 py-8">
        <div className="flex items-end justify-between mb-5">
          <div>
            <h2 className="text-xl font-bold text-md-text-primary">Browse by Demographic</h2>
            <p className="text-sm text-md-text-secondary mt-0.5">
              Find manga targeted for your age group
            </p>
          </div>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { demo: "shounen", label: "Shounen", desc: "Young boys", color: "from-orange-500/20 to-orange-600/10" },
            { demo: "shoujo", label: "Shoujo", desc: "Young girls", color: "from-pink-500/20 to-pink-600/10" },
            { demo: "seinen", label: "Seinen", desc: "Adult men", color: "from-purple-500/20 to-purple-600/10" },
            { demo: "josei", label: "Josei", desc: "Adult women", color: "from-rose-500/20 to-rose-600/10" },
          ].map((item) => (
            <a
              key={item.demo}
              href={`/search?demographic=${item.demo}`}
              className="group relative overflow-hidden rounded-xl bg-md-surface border border-md-border hover:border-md-accent/30 transition-all duration-200 p-6"
            >
              <div className={`absolute inset-0 bg-gradient-to-br ${item.color} opacity-50 group-hover:opacity-70 transition-opacity`} />
              <div className="relative z-10">
                <span className="text-lg font-bold text-md-text-primary group-hover:text-md-accent transition-colors capitalize">
                  {item.label}
                </span>
                <p className="text-sm text-md-text-secondary mt-0.5">{item.desc}</p>
              </div>
            </a>
          ))}
        </div>
      </section>

      <Footer />
    </div>
  );
}
