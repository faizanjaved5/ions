import { useState, useMemo } from "react";
import {
  Search,
  ChevronRight,
  ChevronLeft,
  Sun,
  Moon,
  Menu,
} from "lucide-react";
import { useTheme } from "next-themes";
import { Button } from "./ui/button";
import { Sheet, SheetContent, SheetTrigger } from "./ui/sheet";
import { useIsMobile } from "@/hooks/use-mobile";
import { getIonLocalAsync } from "@/lib/ionMenuAdapter";
import { useEffect } from "react";

type Item = { title: string; url?: string | null; children?: Item[] };

export const IONMenu = () => {
  const { theme, setTheme } = useTheme();
  const [searchQuery, setSearchQuery] = useState("");
  const [useBebasFont, setUseBebasFont] = useState(false);
  const [stack, setStack] = useState<Item[][]>([]);
  const [current, setCurrent] = useState<Item[]>([]);
  useEffect(() => {
    getIonLocalAsync().then((items) => setCurrent(items as unknown as Item[]));
  }, []);
  const [isOpen, setIsOpen] = useState(false);
  const isMobile = useIsMobile();
  const isDarkMode = theme === "dark";

  const filtered = useMemo(() => {
    if (!searchQuery.trim()) return current;
    const q = searchQuery.toLowerCase();
    const filterTree = (items: Item[]): Item[] =>
      items
        .map((i) => {
          const kids = i.children?.length ? filterTree(i.children) : [];
          const match = i.title?.toLowerCase().includes(q);
          if (match || kids.length) return { ...i, children: kids };
          return null;
        })
        .filter(Boolean) as Item[];
    return filterTree(current);
  }, [current, searchQuery]);

  const enter = (item: Item) => {
    if (item.children && item.children.length) {
      setStack((s) => [...s, current]);
      setCurrent(item.children);
      setSearchQuery("");
    } else if (item.url) {
      window.open(item.url, "_blank");
    }
  };

  const back = () => {
    setCurrent((prev) => {
      const nextStack = [...stack];
      const last = nextStack.pop() ?? [];
      setStack(nextStack);
      return last;
    });
  };

  // removed old region/state handlers

  // removed state grid; tree navigation now covers all levels

  const bebasStyles = useBebasFont
    ? "font-bebas text-lg font-normal whitespace-nowrap uppercase tracking-wider"
    : "";
  const menuItemPadding = useBebasFont ? "py-2" : "py-3";

  const MenuContent = () => (
    <div
      className={`w-full border border-menu-border rounded-sm overflow-hidden bg-menu-bg ${
        isMobile ? "h-full border-0 rounded-none" : "max-w-[960px]"
      }`}
    >
      {/* Header */}
      <div className="px-3 md:px-4 py-3 border-b border-menu-border">
        <div className="flex items-center justify-between gap-2 md:gap-4 mb-3 md:mb-0">
          <div className="flex items-center gap-2 md:gap-3 min-w-0">
            {isMobile && stack.length > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={back}
                className="h-8 w-8 p-0"
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
            )}
            <h2 className="text-base md:text-lg font-bold whitespace-nowrap truncate">
              <span className="text-primary">ION</span>{" "}
              <span className="text-foreground">LOCAL NETWORK</span>
            </h2>
          </div>
          <div
            className={`${
              isMobile ? "hidden" : "flex-1 max-w-md relative mx-4 md:mx-[30px]"
            }`}
          >
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Local"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setUseBebasFont(!useBebasFont)}
              className="h-8 px-2 text-xs"
            >
              <span className={useBebasFont ? "font-bebas" : ""}>Aa</span>
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              className="h-8 w-8 p-0"
            >
              {isDarkMode ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
            </Button>
          </div>
        </div>
        {isMobile && (
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Local"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
        )}
      </div>

      {/* Main Content */}
      <div
        className={`flex ${isMobile ? "h-[calc(100vh-80px)]" : "h-[420px]"}`}
      >
        <div className="flex-1 p-[0.5rem] overflow-y-auto">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">
            {filtered.map((item) => {
              const hasChildren = !!(item.children && item.children.length);
              const content = (
                <span className="text-xs uppercase tracking-wide truncate flex-1">
                  <span className="text-primary font-medium">ION</span>{" "}
                  {item.title}
                </span>
              );

              if (hasChildren) {
                return (
                  <button
                    key={item.title}
                    onClick={() => enter(item)}
                    className={`group flex items-center gap-2 px-3 py-2.5 text-left text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 justify-between hover:shadow-sm hover:scale-[1.01] ${
                      useBebasFont
                        ? bebasStyles
                        : "text-xs uppercase tracking-wide"
                    }`}
                  >
                    {content}
                    <ChevronRight className="w-3.5 h-3.5 flex-shrink-0 opacity-50" />
                  </button>
                );
              }

              return (
                <a
                  key={item.title}
                  href={item.url ?? "#"}
                  className={`group flex items-center gap-2 px-3 py-2.5 text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] ${
                    useBebasFont
                      ? bebasStyles
                      : "text-xs uppercase tracking-wide"
                  }`}
                >
                  {content}
                </a>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );

  if (isMobile) {
    return (
      <Sheet open={isOpen} onOpenChange={setIsOpen}>
        <SheetTrigger asChild>
          <Button variant="outline" size="lg" className="w-full max-w-md">
            <Menu className="mr-2 h-5 w-5" />
            Open ION Network Menu
          </Button>
        </SheetTrigger>
        <SheetContent side="bottom" className="h-[95vh] p-0 w-full">
          <MenuContent />
        </SheetContent>
      </Sheet>
    );
  }

  return <MenuContent />;
};
