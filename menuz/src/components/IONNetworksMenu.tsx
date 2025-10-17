import { useState, useMemo } from "react";
import { Search, ChevronRight, ChevronLeft, Sun, Moon } from "lucide-react";
import { useIsMobile } from "@/hooks/use-mobile";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTheme } from "next-themes";
import networksData from "@/data/networksMenuData.json";
import { formatTextWithHighlights } from "@/utils/formatText";

interface NetworkItem {
  title: string;
  url: string | null;
  children: NetworkItem[];
}

interface FlatItem {
  id: string;
  title: string;
  url: string | null;
  level: number;
}

const IONNetworksMenu = () => {
  const [selectedNetwork, setSelectedNetwork] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const [useBebasFont, setUseBebasFont] = useState(false);
  const { theme, setTheme } = useTheme();
  const isMobile = useIsMobile();
  const [mobileView, setMobileView] = useState<"list" | "detail">("list");

  // Flatten nested structure for easier rendering
  const flattenNetwork = (item: NetworkItem, level: number = 0, parentId: string = ""): FlatItem[] => {
    const id = parentId ? `${parentId}-${item.title.toLowerCase().replace(/\s+/g, "-")}` : item.title.toLowerCase().replace(/\s+/g, "-");
    const result: FlatItem[] = [{ id, title: item.title, url: item.url, level }];
    
    if (item.children && item.children.length > 0) {
      item.children.forEach((child) => {
        result.push(...flattenNetwork(child, level + 1, id));
      });
    }
    
    return result;
  };

  const currentNetwork = useMemo(
    () => networksData.networks.find((n) => n.title.toLowerCase().replace(/\s+/g, "-") === selectedNetwork),
    [selectedNetwork]
  );

  const currentNetworkFlattened = useMemo(() => {
    if (!currentNetwork) return [];
    return flattenNetwork(currentNetwork).slice(1); // Skip the parent itself
  }, [currentNetwork]);

  const filteredItems = useMemo(() => {
    if (!searchQuery.trim()) return [];

    const query = searchQuery.toLowerCase();
    const results: FlatItem[] = [];

    const searchInNetwork = (item: NetworkItem, parentId: string = "") => {
      const id = parentId ? `${parentId}-${item.title.toLowerCase().replace(/\s+/g, "-")}` : item.title.toLowerCase().replace(/\s+/g, "-");
      
      if (item.title.toLowerCase().includes(query)) {
        results.push({ id, title: item.title, url: item.url, level: 0 });
      }

      if (item.children && item.children.length > 0) {
        item.children.forEach((child) => searchInNetwork(child, id));
      }
    };

    networksData.networks.forEach((network) => searchInNetwork(network));
    return results;
  }, [searchQuery]);

  const handleNetworkClick = (item: FlatItem | NetworkItem) => {
    const id = 'id' in item ? item.id : item.title.toLowerCase().replace(/\s+/g, "-");
    setSelectedNetwork(id);
    setSearchQuery("");
    if (isMobile) {
      setMobileView("detail");
    }
  };

  const handleMobileBack = () => {
    setSelectedNetwork(null);
    if (isMobile) {
      setMobileView("list");
    }
  };

  const bebasStyles = useBebasFont ? 'font-bebas text-lg font-normal whitespace-nowrap uppercase tracking-wider' : '';
  const menuItemPadding = useBebasFont ? 'py-2.5' : 'py-3';

  const highlightION = (text: string) => {
    if (text.includes('ION')) {
      const parts = text.split('ION');
      return (
        <>
          {parts[0]}
          <span className="text-primary font-medium">ION</span>
          {parts[1]}
        </>
      );
    }
    return text;
  };

  const renderNetworkItems = () => {
    if (!currentNetwork) return null;

    // Group items by level
    const level1Items: NetworkItem[] = currentNetwork.children || [];
    const itemsWithoutChildren: NetworkItem[] = [];
    const itemsWithChildren: NetworkItem[] = [];

    level1Items.forEach(item => {
      if (item.children && item.children.length > 0) {
        itemsWithChildren.push(item);
      } else {
        itemsWithoutChildren.push(item);
      }
    });

    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-[0px]">
        {/* Column for items without children */}
        {itemsWithoutChildren.length > 0 && (
          <div className="flex flex-col gap-[0px]">
            {itemsWithoutChildren.map((item) => {
              const content = (
                <span className={`text-sm font-medium text-card-foreground group-hover:text-primary ${!useBebasFont ? 'text-sm' : ''}`}>
                  {formatTextWithHighlights(item.title)}
                </span>
              );

              if (item.url) {
                return (
                  <a
                    key={item.title}
                    href={item.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasStyles}`}
                  >
                    {content}
                  </a>
                );
              }

              return (
                <div
                  key={item.title}
                  className={`group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} ${bebasStyles} opacity-60`}
                >
                  {content}
                </div>
              );
            })}
          </div>
        )}

        {/* Columns for parent items with their children */}
        {itemsWithChildren.map((parentItem) => (
          <div key={parentItem.title} className="flex flex-col gap-[0px]">
            {/* Parent header */}
            <div className={`rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} ${bebasStyles}`}>
              <span className="text-sm font-medium text-muted-foreground">
                {formatTextWithHighlights(parentItem.title)}
              </span>
            </div>
            
            {/* Children */}
            {parentItem.children.map((child) => {
              const content = (
                <span className={`text-sm font-medium text-card-foreground group-hover:text-primary ${!useBebasFont ? 'text-sm' : ''}`}>
                  {formatTextWithHighlights(child.title)}
                </span>
              );

              if (child.url) {
                return (
                  <a
                    key={child.title}
                    href={child.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasStyles}`}
                  >
                    {content}
                  </a>
                );
              }

              return (
                <div
                  key={child.title}
                  className={`group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} ${bebasStyles} opacity-60`}
                >
                  {content}
                </div>
              );
            })}
          </div>
        ))}
      </div>
    );
  };

  return (
    <div
      className={`flex h-[520px] flex-col rounded-lg border border-border bg-card ${bebasStyles} overflow-hidden`}
    >
      {/* Header */}
      <div className="px-3 md:px-6 py-3 md:py-4 border-b border-border">
        <div className="flex items-center justify-between gap-2 md:gap-4 mb-0">
          <div className="flex items-center gap-2 md:gap-3 min-w-0">
            {selectedNetwork && (
              <Button
                variant="ghost"
                size="icon"
                onClick={handleMobileBack}
                className="h-8 w-8"
                title="Back to networks"
              >
                <ChevronLeft className="h-5 w-5" />
              </Button>
            )}
            <h2 className="text-base md:text-lg font-bold whitespace-nowrap truncate">
              {currentNetwork ? (
                formatTextWithHighlights(currentNetwork.title)
              ) : (
                <>
                  <span className="text-primary">ION</span>{" "}
                  <span className="text-foreground">NETWORKS</span>
                </>
              )}
            </h2>
          </div>

          <div className={`${isMobile ? 'hidden' : 'flex-1 max-w-md relative mx-4 md:mx-[30px]'}`}>
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Networks"
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
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              className="h-8 w-8"
            >
              {theme === "dark" ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
            </Button>
          </div>
        </div>

        {isMobile && (
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Networks"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
        )}
      </div>

      {/* Content */}
      <ScrollArea className="flex-1">
        <div className="p-2">
          {searchQuery ? (
            <div className="space-y-[1px]">
              {filteredItems.length > 0 ? (
                filteredItems.map((item) => {
                  const isTopLevel = networksData.networks.some(
                    (n) => n.title.toLowerCase().replace(/\s+/g, "-") === item.id
                  );

                  // Find if this item has children in the original data
                  const findItemHasChildren = (id: string): boolean => {
                    const parts = id.split('-');
                    let current: NetworkItem | undefined;
                    
                    for (const network of networksData.networks) {
                      if (network.title.toLowerCase().replace(/\s+/g, '-') === parts[0]) {
                        current = network;
                        break;
                      }
                    }
                    
                    if (!current) return false;
                    
                    for (let i = 1; i < parts.length; i++) {
                      const found = current.children?.find(child => 
                        child.title.toLowerCase().replace(/\s+/g, '-') === parts[i]
                      );
                      if (!found) return false;
                      current = found;
                    }
                    
                    return current?.children ? current.children.length > 0 : false;
                  };

                  const hasChildren = findItemHasChildren(item.id);

                  if (isTopLevel) {
                    return (
                      <button
                        key={item.id}
                        onClick={() => {
                          handleNetworkClick(item);
                          setSearchQuery("");
                        }}
                        className={`flex w-full items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} text-left transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasStyles}`}
                      >
                        <span className="text-sm font-medium text-card-foreground">
                          {formatTextWithHighlights(item.title)}
                        </span>
                        {hasChildren && <ChevronRight className="h-4 w-4 text-muted-foreground" />}
                      </button>
                    );
                  }

                  if (item.url) {
                    return (
                      <a
                        key={item.id}
                        href={item.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasStyles}`}
                      >
                        <span className="text-sm text-card-foreground">
                          {formatTextWithHighlights(item.title)}
                        </span>
                        {hasChildren && <ChevronRight className="h-4 w-4 text-muted-foreground" />}
                      </a>
                    );
                  }

                  return (
                    <div
                      key={item.id}
                      className={`flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} ${bebasStyles} opacity-60`}
                    >
                      <span className="text-sm text-card-foreground">
                        {formatTextWithHighlights(item.title)}
                      </span>
                    </div>
                  );
                })
              ) : (
                <p className="py-8 text-center text-sm text-muted-foreground">
                  No results found for "{searchQuery}"
                </p>
              )}
            </div>
          ) : currentNetwork ? (
            <div className="space-y-4">
              {renderNetworkItems()}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-[0px]">
              {networksData.networks.map((network) => {
                const hasChildren = network.children && network.children.length > 0;
                return (
                   <button
                    key={network.title}
                    onClick={() => handleNetworkClick(network)}
                    className={`group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${menuItemPadding} text-left transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasStyles}`}
                  >
                    <span className="text-sm font-medium text-card-foreground group-hover:text-primary">
                      {formatTextWithHighlights(network.title)}
                    </span>
                    {hasChildren && <ChevronRight className="h-4 w-4 text-muted-foreground transition-colors group-hover:text-primary" />}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </ScrollArea>
    </div>
  );
};

export default IONNetworksMenu;
