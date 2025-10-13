import { useState, useMemo, useEffect, useRef } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ScrollArea } from "@/components/ui/scroll-area";
import StoreCard from "./StoreCard";
import mallOfChampionsStores from "@/data/mallOfChampionsStores.json";
import { Loader2 } from "lucide-react";

interface Store {
  s: string; // store name
  url: string; // partial URL
  img: string; // partial image path
  tab: string; // tab category
}

const ITEMS_PER_PAGE = 30;

const StoresTabs = () => {
  const [activeTab, setActiveTab] = useState("Store");
  const [visibleCounts, setVisibleCounts] = useState<Record<string, number>>({});
  const loadMoreRefs = useRef<Record<string, HTMLDivElement | null>>({});

  // Group stores by tab and get unique tabs
  const { storesByTab, tabs } = useMemo(() => {
    const grouped = mallOfChampionsStores.reduce((acc: Record<string, Store[]>, store) => {
      if (!acc[store.tab]) {
        acc[store.tab] = [];
      }
      acc[store.tab].push(store);
      return acc;
    }, {});

    // Sort tabs with "Store" first, then alphabetically
    const sortedTabs = Object.keys(grouped).sort((a, b) => {
      if (a === "Store") return -1;
      if (b === "Store") return 1;
      return a.localeCompare(b);
    });

    return { storesByTab: grouped, tabs: sortedTabs };
  }, []);

  // Add a "View All" tab that shows all stores
  const allTabs = ["View All", ...tabs];

  // Initialize visible counts for all tabs
  useEffect(() => {
    const initialCounts: Record<string, number> = { "View All": ITEMS_PER_PAGE };
    tabs.forEach(tab => {
      initialCounts[tab] = ITEMS_PER_PAGE;
    });
    setVisibleCounts(initialCounts);
  }, [tabs]);

  // Setup intersection observer for infinite scroll
  useEffect(() => {
    const observers: IntersectionObserver[] = [];

    Object.keys(loadMoreRefs.current).forEach(tab => {
      const element = loadMoreRefs.current[tab];
      if (!element) return;

      const observer = new IntersectionObserver(
        (entries) => {
          if (entries[0].isIntersecting) {
            setVisibleCounts(prev => {
              const currentCount = prev[tab] || ITEMS_PER_PAGE;
              const maxCount = tab === "View All" 
                ? mallOfChampionsStores.length 
                : (storesByTab[tab]?.length || 0);
              
              if (currentCount < maxCount) {
                return { ...prev, [tab]: Math.min(currentCount + ITEMS_PER_PAGE, maxCount) };
              }
              return prev;
            });
          }
        },
        { threshold: 0.1 }
      );

      observer.observe(element);
      observers.push(observer);
    });

    return () => {
      observers.forEach(observer => observer.disconnect());
    };
  }, [storesByTab, activeTab]);

  const getVisibleStores = (tab: string, stores: Store[]) => {
    const count = visibleCounts[tab] || ITEMS_PER_PAGE;
    return stores.slice(0, count);
  };

  const hasMore = (tab: string, totalCount: number) => {
    const count = visibleCounts[tab] || ITEMS_PER_PAGE;
    return count < totalCount;
  };

  return (
    <div className="space-y-4">
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <ScrollArea className="w-full pb-2">
          <TabsList className="inline-flex h-auto p-1 bg-muted/50 rounded-lg">
            {allTabs.map((tab) => (
              <TabsTrigger
                key={tab}
                value={tab}
                className="px-3 py-2 text-xs font-medium rounded-md whitespace-nowrap data-[state=active]:bg-background data-[state=active]:text-foreground"
              >
                {tab} {tab === "View All" ? `(${mallOfChampionsStores.length})` : `(${storesByTab[tab]?.length || 0})`}
              </TabsTrigger>
            ))}
          </TabsList>
        </ScrollArea>

        <div className="mt-4">
          <TabsContent value="View All" className="mt-0">
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
              {getVisibleStores("View All", mallOfChampionsStores).map((store, index) => (
                <StoreCard key={`${store.s}-${index}`} store={store} />
              ))}
            </div>
            {hasMore("View All", mallOfChampionsStores.length) && (
              <div 
                ref={(el) => loadMoreRefs.current["View All"] = el}
                className="flex justify-center py-8"
              >
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
              </div>
            )}
          </TabsContent>

          {tabs.map((tab) => (
            <TabsContent key={tab} value={tab} className="mt-0">
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                {getVisibleStores(tab, storesByTab[tab] || []).map((store, index) => (
                  <StoreCard key={`${store.s}-${index}`} store={store} />
                ))}
              </div>
              {hasMore(tab, storesByTab[tab]?.length || 0) && (
                <div 
                  ref={(el) => loadMoreRefs.current[tab] = el}
                  className="flex justify-center py-8"
                >
                  <Loader2 className="h-6 w-6 animate-spin text-primary" />
                </div>
              )}
            </TabsContent>
          ))}
        </div>
      </Tabs>
    </div>
  );
};

export default StoresTabs;