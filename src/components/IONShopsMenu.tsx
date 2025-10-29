import { useState, useMemo } from "react";
import { Search, ChevronRight, ChevronLeft, Sun, Moon, ShoppingBag, X } from "lucide-react";
import { useIsMobile } from "@/hooks/use-mobile";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTheme } from "next-themes";
import shopsData from "@/data/shopsMenuData.json";
import StoresTabs from "./StoresTabs";

// Utility function to get sport icon ID from item name
const getSportIconId = (itemName: string): string | null => {
  // Extract sport name from "ION [Sport]" format
  const sportMatch = itemName.match(/ION\s+(.+)/i);
  if (!sportMatch) return null;
  
  const sport = sportMatch[1].toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[()]/g, '');
  
  return `ion-${sport}`;
};

// Utility function to get league icon ID from item name
const getLeagueIconId = (itemName: string): string | null => {
  const league = itemName.toLowerCase()
    .replace(/\s+/g, '-');
  
  return `ion-${league}`;
};

// Utility function to get section icon ID from item name
const getSectionIconId = (itemName: string): string | null => {
  // Extract the part after "ION " and convert to kebab-case
  let section = itemName
    .replace(/^ION\s+/i, '')
    .toLowerCase()
    .replace(/\s*&\s*/g, '-')  // Replace & with dash (handle spaces around it)
    .replace(/\s*\|\s*/g, '-')  // Replace | with dash (handle spaces around it)
    .replace(/\s+/g, '-')       // Replace remaining spaces with dash
    .replace(/-+/g, '-')        // Replace multiple dashes with single dash
    .replace(/^-|-$/g, '');     // Remove leading/trailing dashes
  
  // Special case mappings
  if (section.includes('give-front') || section.includes('pay')) {
    section = 'pay';
  }
  
  return `ion-${section}`;
};

// Mapping of school names to local logo filenames
const schoolLogoMap: Record<string, string> = {
  "Alabama State University": "Alabama_State_University.svg",
  "Appalachian State University": "Appalachian_State_University.svg",
  "Arizona State University": "Arizona_State_University.svg",
  "Auburn University": "Auburn_University.svg",
  "Austin Peay State University": "Austin_Peay_State_University.svg",
  "Baylor University": "Baylor_University.svg",
  "Brown University": "Brown_University.svg",
  "Buffalo State College": "Buffalo_State_College.png",
  "California Polytechnic State University": "California_Polytechnic_State_University.png",
  "California State University Chico": "California_State_University_Chico.png",
};

interface ShopItem {
  name: string;
  url: string;
}

interface ShopCategory {
  id: string;
  name: string;
  url: string;
  items: ShopItem[];
}

interface IONShopsMenuProps {
  onClose?: () => void;
  externalTheme?: "dark" | "light";
  onExternalThemeToggle?: () => void;
}

const IONShopsMenu = ({ onClose, externalTheme, onExternalThemeToggle }: IONShopsMenuProps = {}) => {
  const [selectedCategory, setSelectedCategory] = useState<string | null>(shopsData.categories?.[0]?.id ?? null);
  const [searchQuery, setSearchQuery] = useState("");
  const [useBebasFont, setUseBebasFont] = useState(false);
  const { theme, setTheme } = useTheme();
  const activeTheme = externalTheme || theme;
  const handleThemeToggle = () => {
    if (onExternalThemeToggle) onExternalThemeToggle();
    else setTheme(theme === "dark" ? "light" : "dark");
  };
  const isMobile = useIsMobile();
  const [mobileView, setMobileView] = useState<"list" | "detail">("list");

  const currentCategory = useMemo(
    () => shopsData.categories.find((c) => c.id === selectedCategory),
    [selectedCategory]
  );

  const filteredItems = useMemo(() => {
    if (!searchQuery.trim()) return [];

    const query = searchQuery.toLowerCase();
    const results: Array<{ type: string; category?: ShopCategory; item?: ShopItem; categoryName?: string }> = [];

    shopsData.categories.forEach((category) => {
      if (category.name.toLowerCase().includes(query)) {
        results.push({ type: "category", category });
      }

      category.items?.forEach((item) => {
        if (item.name.toLowerCase().includes(query)) {
          results.push({ type: "item", item, categoryName: category.name });
        }
      });
    });

    return results;
  }, [searchQuery]);

  const handleCategoryClick = (categoryId: string) => {
    setSelectedCategory(categoryId);
    setSearchQuery("");
    if (isMobile) {
      setMobileView("detail");
    }
  };

  const handleMobileBack = () => {
    setSelectedCategory(null);
    if (isMobile) {
      setMobileView("list");
    }
  };

  const bebasStyles = useBebasFont ? 'font-bebas font-normal whitespace-nowrap uppercase tracking-wider' : '';
  const menuItemPadding = useBebasFont ? 'py-2.5' : 'py-3';

  const renderItemsGrid = (items: ShopItem[]) => (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-[0.35rem]">
      {items.map((item) => {
        // Check if this is a school and we have a local logo
        const localLogo = currentCategory?.id === 'by-school' ? schoolLogoMap[item.name] : null;
        
        // Determine icon based on category (only if not using local logo)
        let iconId: string | null = null;
        if (!localLogo) {
          if (currentCategory?.id === 'by-league') {
            iconId = getLeagueIconId(item.name);
          } else if (currentCategory?.id === 'by-category') {
            iconId = getSectionIconId(item.name);
          } else if (currentCategory?.id === 'by-town') {
            iconId = 'ion-local-shop';
          } else if (currentCategory?.id === 'by-brand') {
            iconId = 'ion-brand';
          } else {
            iconId = getSportIconId(item.name);
          }
        }
        
        return (
          <a
            key={item.url}
            href={`https://mallofchampions.com/${item.url}`}
            target="_blank"
            rel="noopener noreferrer"
            className="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105"
          >
            {localLogo ? (
              <div className="mb-3 flex h-20 w-20 items-center justify-center rounded-lg bg-white/90 dark:bg-white/95 p-2 overflow-hidden border border-border/20 group-hover:shadow-md transition-all duration-200">
                <img
                  src={`/Logos/${localLogo}`}
                  alt={`${item.name} logo`}
                  className="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-200"
                  onError={(e) => {
                    const target = e.target as HTMLImageElement;
                    target.style.display = "none";
                    const fallback = target.nextElementSibling as HTMLElement;
                    if (fallback) fallback.style.display = "flex";
                  }}
                />
                <div className="hidden h-full w-full items-center justify-center bg-background/50 rounded">
                  <ShoppingBag className="h-8 w-8 text-primary group-hover:scale-110 transition-transform" />
                </div>
              </div>
            ) : (
              <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200">
                {iconId ? (
                  <svg width="32" height="32" className="text-primary group-hover:scale-110 transition-transform" aria-label={item.name}>
                    <use href={`#${iconId}`} />
                  </svg>
                ) : (
                  <ShoppingBag className="h-8 w-8 text-primary group-hover:scale-110 transition-transform" />
                )}
              </div>
            )}
            <span className="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
              {item.name}
            </span>
          </a>
        );
      })}
    </div>
  );

  return (
    <div
      className={`flex h-[520px] flex-col rounded-lg border border-border bg-card ${bebasStyles} overflow-hidden`}
    >
      {/* Header */}
      <div className="px-3 md:px-6 py-3 md:py-4 border-b border-border">
        <div className="flex items-center justify-between gap-2 md:gap-4 mb-0">
          <div className="flex items-center gap-2 md:gap-3 min-w-0">
            {isMobile && selectedCategory && (
              <Button
                variant="ghost"
                size="icon"
                onClick={handleMobileBack}
                className="h-8 w-8"
              >
                <ChevronLeft className="h-5 w-5" />
              </Button>
            )}
            <h2 className="text-base md:text-lg font-bold whitespace-nowrap truncate">
              <span className="text-foreground">MALL OF </span>
              <span className="text-primary">CHAMPIONS</span>
            </h2>
          </div>

          <div className={`${isMobile ? 'hidden' : 'flex-1 max-w-md relative mx-4 md:mx-[30px]'}`}>
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search the Mall of Champions"
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
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search the Mall of Champions"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
        )}
      </div>

      {/* Main Content */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left Sidebar - Categories */}
        <div
          className={`${
            isMobile && mobileView === "detail" ? "hidden" : ""
          } w-40 sm:w-48 md:w-56 border-r border-menu-border overflow-y-auto overflow-x-hidden flex-shrink-0`}
        >
          {shopsData.categories.map((category) => {
            const parts = category.name.split('ION');
            return (
              <button
                key={category.id}
                onClick={() => handleCategoryClick(category.id)}
                className={`w-full flex items-center justify-between px-4 ${menuItemPadding} text-left transition-all duration-200 border-b border-menu-border ${
                  useBebasFont ? bebasStyles : 'text-sm uppercase tracking-wide'
                } ${
                  !searchQuery && selectedCategory === category.id
                    ? "border-l-2 border-l-primary bg-menu-item-active shadow-sm"
                    : "hover:bg-menu-item-hover hover:shadow-sm hover:translate-x-[2px]"
                }`}
              >
                <span>
                  {parts.length > 1 ? (
                    <>
                      {parts[0]}
                      <span className="text-primary font-medium">ION</span>
                      {parts[1]}
                    </>
                  ) : (
                    category.name
                  )}
                </span>
                <ChevronRight className="w-4 h-4 text-muted-foreground flex-shrink-0" />
              </button>
            );
          })}
        </div>

        {/* Right Content Area */}
        <ScrollArea className={`flex-1 ${isMobile && mobileView === "list" ? "hidden" : ""}`}>
          <div className="p-2">
            {searchQuery ? (
              <div className="space-y-3">
                {filteredItems.length > 0 ? (
                  filteredItems.map((result, index) => {
                    if (result.type === "category" && result.category) {
                      const parts = result.category.name.split('ION');
                      return (
                        <button
                          key={`cat-${result.category.id}`}
                          onClick={() => {
                            handleCategoryClick(result.category!.id);
                            setSearchQuery("");
                          }}
                          className="flex w-full items-center justify-between rounded-lg border border-border/50 bg-card p-3 text-left transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md hover:scale-[1.02]"
                        >
                          <span className="text-sm font-medium text-card-foreground">
                            {parts.length > 1 ? (
                              <>
                                {parts[0]}
                                <span className="text-primary font-medium">ION</span>
                                {parts[1]}
                              </>
                            ) : (
                              result.category.name
                            )}
                          </span>
                          <ChevronRight className="h-4 w-4 text-muted-foreground" />
                        </button>
                      );
                    }

                    if (result.type === "item" && result.item) {
                      // Get the category to determine which icon function to use
                      const itemCategory = shopsData.categories.find(cat => 
                        cat.items?.some(item => item.name === result.item?.name)
                      );
                      let iconId: string | null = null;
                      if (itemCategory?.id === 'by-league') {
                        iconId = getLeagueIconId(result.item.name);
                      } else if (itemCategory?.id === 'by-category') {
                        iconId = getSectionIconId(result.item.name);
                      } else if (itemCategory?.id === 'by-town') {
                        iconId = 'ion-local-shop';
                      } else if (itemCategory?.id === 'by-brand') {
                        iconId = 'ion-brand';
                      } else {
                        iconId = getSportIconId(result.item.name);
                      }
                      
                      return (
                        <a
                          key={`item-${index}`}
                          href={`https://mallofchampions.com/${result.item.url}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center justify-between rounded-lg border border-border/50 bg-card p-3 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md hover:scale-[1.02]"
                        >
                          <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                              {iconId ? (
                                <svg width="20" height="20" className="text-primary" aria-label={result.item.name}>
                                  <use href={`#${iconId}`} />
                                </svg>
                              ) : (
                                <ShoppingBag className="h-5 w-5 text-primary" />
                              )}
                            </div>
                            <div>
                              <p className="text-sm font-medium text-card-foreground">
                                {result.item.name}
                              </p>
                              <p className="text-xs text-muted-foreground">{result.categoryName}</p>
                            </div>
                          </div>
                          <ChevronRight className="h-4 w-4 text-muted-foreground" />
                        </a>
                      );
                    }

                    return null;
                  })
                ) : (
                  <p className="py-8 text-center text-sm text-muted-foreground">
                    No results found for "{searchQuery}"
                  </p>
                )}
              </div>
            ) : currentCategory ? (
              <div className="space-y-4">
                {currentCategory.id === 'all-stores' ? (
                  <>
                    <div className="mb-4">
                      <h3 className="text-xl font-bold">
                        {currentCategory.name.includes('ION') ? (
                          <>
                            {currentCategory.name.split('ION')[0]}
                            <span className="text-primary">ION</span>
                            {currentCategory.name.split('ION')[1]}
                          </>
                        ) : (
                          currentCategory.name
                        )}
                      </h3>
                    </div>
                    <StoresTabs />
                  </>
                ) : currentCategory.items && currentCategory.items.length > 0 ? (
                  <>
                    <div className="mb-4 flex items-center justify-between">
                      <h3 className="text-xl font-bold">
                        {currentCategory.name.includes('ION') ? (
                          <>
                            {currentCategory.name.split('ION')[0]}
                            <span className="text-primary">ION</span>
                            {currentCategory.name.split('ION')[1]}
                          </>
                        ) : (
                          currentCategory.name
                        )}
                      </h3>
                      <a
                        href={`https://mallofchampions.com/${currentCategory.url}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-primary hover:underline whitespace-nowrap"
                      >
                        View all →
                      </a>
                    </div>
                    {renderItemsGrid(currentCategory.items)}
                  </>
                
                ) : (
                  <a
                    href={`https://mallofchampions.com/${currentCategory.url}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center justify-center rounded-lg border-2 border-dashed border-primary/30 bg-primary/5 p-8 transition-all hover:border-primary/50 hover:bg-primary/10"
                  >
                    <div className="text-center">
                      <ShoppingBag className="mx-auto h-12 w-12 text-primary mb-3" />
                      <p className="text-sm font-medium text-card-foreground">Visit Store</p>
                      <p className="text-xs text-muted-foreground mt-1">Browse all products</p>
                    </div>
                  </a>
                )}
              </div>
            ) : (
              <div className="flex items-center justify-center h-full">
                <div className="text-center">
                  <ShoppingBag className="mx-auto h-16 w-16 text-muted-foreground/30 mb-4" />
                  <p className="text-sm text-muted-foreground">
                    Select a category to browse products
                  </p>
                </div>
              </div>
            )}
          </div>
        </ScrollArea>
      </div>
    </div>
  );
};

export default IONShopsMenu;
