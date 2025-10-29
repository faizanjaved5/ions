import { useState, useMemo } from "react";
import { Search, Sun, Moon, ShoppingBag, Grid3x3, List, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTheme } from "next-themes";
import { Link } from "react-router-dom";
import { useIsMobile } from "@/hooks/use-mobile";
import sportsData from "@/data/sportsMenuData.json";

interface SportItem {
  name: string;
  url: string;
  icon?: string;
}

interface Category {
  name: string;
  emoji: string;
  sports: SportItem[];
}

interface IONSportsMenuProps {
  onClose?: () => void;
  linkType?: "router" | "anchor";
  spriteUrl?: string;
  externalTheme?: "dark" | "light";
  onExternalThemeToggle?: () => void;
}

const IONSportsMenu = ({ onClose, linkType = "router", spriteUrl, externalTheme, onExternalThemeToggle }: IONSportsMenuProps = {}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [useBebasFont, setUseBebasFont] = useState(false);
  const [viewMode, setViewMode] = useState<"card" | "list">("card");
  const { theme, setTheme } = useTheme();
  const isMobile = useIsMobile();
  const activeTheme = externalTheme || theme;
  const handleThemeToggle = () => {
    if (onExternalThemeToggle) onExternalThemeToggle();
    else setTheme(theme === "dark" ? "light" : "dark");
  };

  // Get all sports in a flat array for card view (deduplicated by URL)
  const allSports = useMemo(() => {
    const sportsMap = new Map<string, SportItem>();
    sportsData.categories.forEach((category: Category) => {
      category.sports.forEach((sport) => {
        // Use URL as unique key to prevent duplicates
        if (!sportsMap.has(sport.url)) {
          sportsMap.set(sport.url, sport);
        }
      });
    });
    return Array.from(sportsMap.values()).sort((a, b) => a.name.localeCompare(b.name));
  }, []);

  const filteredSports = useMemo(() => {
    if (!searchQuery.trim()) return allSports;

    const query = searchQuery.toLowerCase();
    return allSports.filter((sport) =>
      sport.name.toLowerCase().includes(query)
    );
  }, [searchQuery, allSports]);

  const filteredCategories = useMemo(() => {
    if (!searchQuery.trim()) return sportsData.categories;

    const query = searchQuery.toLowerCase();
    return sportsData.categories
      .map((category: Category) => ({
        ...category,
        sports: category.sports.filter((sport) =>
          sport.name.toLowerCase().includes(query)
        ),
      }))
      .filter((category) => category.sports.length > 0);
  }, [searchQuery]);

  const bebasStyles = useBebasFont ? 'font-bebas font-normal whitespace-nowrap uppercase tracking-wider' : '';

  return (
    <div
      className={`flex h-[520px] flex-col rounded-lg border border-border bg-card ${bebasStyles} overflow-hidden`}
    >
      {/* Header */}
      <div className="px-3 md:px-6 py-3 md:py-4 border-b border-border">
        <div className="flex items-center justify-between gap-2 md:gap-4 mb-0">
          <div className="flex items-center gap-2 md:gap-3 min-w-0">
            <h2 className="text-base md:text-lg font-bold whitespace-nowrap truncate">
              <span className="text-primary">ION</span>
              <span className="text-foreground">SPORTS</span>
            </h2>
          </div>

          <div className={`${isMobile ? 'hidden' : 'flex-1 max-w-md relative mx-4 md:mx-[30px]'}`}>
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Sports"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setViewMode(viewMode === "card" ? "list" : "card")}
              className="h-8 w-8"
              title={viewMode === "card" ? "Switch to List View" : "Switch to Card View"}
            >
              {viewMode === "card" ? (
                <List className="h-4 w-4" />
              ) : (
                <Grid3x3 className="h-4 w-4" />
              )}
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setUseBebasFont(!useBebasFont)}
              className="h-8 w-8"
            >
              <span className={useBebasFont ? 'font-bebas' : ''}>Aa</span>
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={handleThemeToggle}
              className="h-8 w-8"
            >
              {activeTheme === "dark" ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
            </Button>
            {onClose && (
              <Button
                variant="ghost"
                size="icon"
                onClick={onClose}
                className="h-8 w-8"
              >
                <X className="h-4 w-4" />
                <span className="sr-only">Close</span>
              </Button>
            )}
          </div>
        </div>

        {isMobile && (
          <div className="relative w-full mt-3">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Sports"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
        )}
      </div>

      {/* Sports Content */}
      <ScrollArea className="flex-1">
        <div className="p-4">
          {viewMode === "card" ? (
            // Card View - Alphabetical grid
            filteredSports.length > 0 ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-[0.35rem]">
                {filteredSports.map((sport: SportItem) => (
                  linkType === "anchor" ? (
                  <a
                    key={sport.url}
                    href={sport.url}
                    className="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105"
                  >
                    <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200">
                      {sport.icon ? (
                        <svg width="32" height="32" className="text-primary group-hover:scale-110 transition-transform" aria-label={sport.name}>
                          <use href={`${spriteUrl ? `${spriteUrl}#${sport.icon}` : `#${sport.icon}`}`} />
                        </svg>
                      ) : (
                        <ShoppingBag className="h-8 w-8 text-primary group-hover:scale-110 transition-transform" />
                      )}
                    </div>
                    <span className="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
                      {sport.name}
                    </span>
                  </a>
                  ) : (
                  <Link
                    key={sport.url}
                    to={sport.url}
                    className="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105"
                  >
                    <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200">
                      {sport.icon ? (
                        <svg width="32" height="32" className="text-primary group-hover:scale-110 transition-transform" aria-label={sport.name}>
                          <use href={`#${sport.icon}`} />
                        </svg>
                      ) : (
                        <ShoppingBag className="h-8 w-8 text-primary group-hover:scale-110 transition-transform" />
                      )}
                    </div>
                    <span className="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
                      {sport.name}
                    </span>
                  </Link>
                  )
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No sports found for "{searchQuery}"
              </p>
            )
          ) : (
            // List View - Grouped by category
            filteredCategories.length > 0 ? (
              <div className="space-y-6">
                {filteredCategories.map((category: Category) => (
                  <div key={category.name}>
                    <h3 className="text-xl font-bold mb-3 flex items-center gap-2 text-foreground">
                      <span className="text-2xl">{category.emoji}</span>
                      <span>{category.name}</span>
                    </h3>
                    <div className="space-y-1">
                      {category.sports.map((sport: SportItem) => (
                        linkType === "anchor" ? (
                        <a
                          key={sport.url}
                          href={sport.url}
                          className="group flex items-center gap-3 rounded-lg border border-border/30 bg-card px-4 py-2.5 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md"
                        >
                          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200 flex-shrink-0">
                            {sport.icon ? (
                              <svg width="20" height="20" className="text-primary group-hover:scale-110 transition-transform" aria-label={sport.name}>
                                <use href={`${spriteUrl ? `${spriteUrl}#${sport.icon}` : `#${sport.icon}`}`} />
                              </svg>
                            ) : (
                              <ShoppingBag className="h-5 w-5 text-primary group-hover:scale-110 transition-transform" />
                            )}
                          </div>
                          <span className="text-sm font-medium text-card-foreground group-hover:text-primary transition-colors">
                            {sport.name}
                          </span>
                        </a>
                        ) : (
                        <Link
                          key={sport.url}
                          to={sport.url}
                          className="group flex items-center gap-3 rounded-lg border border-border/30 bg-card px-4 py-2.5 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md"
                        >
                          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200 flex-shrink-0">
                            {sport.icon ? (
                              <svg width="20" height="20" className="text-primary group-hover:scale-110 transition-transform" aria-label={sport.name}>
                                <use href={`#${sport.icon}`} />
                              </svg>
                            ) : (
                              <ShoppingBag className="h-5 w-5 text-primary group-hover:scale-110 transition-transform" />
                            )}
                          </div>
                          <span className="text-sm font-medium text-card-foreground group-hover:text-primary transition-colors">
                            {sport.name}
                          </span>
                        </Link>
                        )
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No sports found for "{searchQuery}"
              </p>
            )
          )}
        </div>
      </ScrollArea>
    </div>
  );
};

export default IONSportsMenu;
