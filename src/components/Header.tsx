import { useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { Search, Upload, Moon, Sun, Menu, Bell, Loader2 } from "lucide-react";
import { useTheme } from "next-themes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import * as PopoverPrimitive from "@radix-ui/react-popover";
import ionLogo from "@/assets/ion-logo-gold.png";
import { IONMenu } from "@/components/IONMenu";
import IONNetworksMenu from "@/components/IONNetworksMenu";
import IONInitiativesMenu from "@/components/IONInitiativesMenu";
import IONShopsMenu from "@/components/IONShopsMenu";
import IONConnectionsMenu from "@/components/IONConnectionsMenu";
import UserProfile from "@/components/UserProfile";
import NotificationsPanel from "@/components/NotificationsPanel";


const Header = () => {
  const { isLoggedIn, user, logout, login } = useAuth();
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  type SearchResultItem = {
    id: string;
    title: string;
    description?: string | null;
    thumbnail?: string;
    link: string;
    date?: string | null;
    category?: string | null;
    channel_title?: string | null;
    type?: string | null;
    location?: string | null;
    excerpt?: string | null;
    source_domain?: string | null;
    relative_date?: string | null;
  };
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchResults, setSearchResults] = useState<SearchResultItem[]>([]);
  const [searchTotal, setSearchTotal] = useState<number | null>(null);
  const [ionLocalOpen, setIonLocalOpen] = useState(false);
  const [ionNetworksOpen, setIonNetworksOpen] = useState(false);
  const [ionInitiativesOpen, setIonInitiativesOpen] = useState(false);
  const [ionMallOpen, setIonMallOpen] = useState(false);
  const [connectionsOpen, setConnectionsOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const { theme, setTheme } = useTheme();
  
  // Default header buttons (these could also come from your JSON)
  const headerButtons = {
    upload: { label: "Upload", link: "/upload", visible: true },
    signIn: { label: "Sign In", link: "/signin", visible: true }
  };
  
  const notifications = user?.notifications || [];
  const unreadCount = notifications.filter(n => !n.read).length;

  const handleLogout = () => {
    logout();
  };

  const handleSignIn = () => {
    login();
  };

  const normalizeUrl = (url?: string | null): string => {
    if (!url) return "#";
    const trimmed = url.trim();
    if (!trimmed) return "#";
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    if (/^www\./i.test(trimmed)) return `https://${trimmed}`;
    if (/^\//.test(trimmed)) return `https://www.ions.com${trimmed}`;
    return trimmed;
  };

  const dedupeResults = (items: SearchResultItem[]): SearchResultItem[] => {
    const seen = new Set<string>();
    return items.filter((it) => {
      const key = it.id || it.link || JSON.stringify(it);
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  };

  const runSearch = async () => {
    const term = searchQuery.trim();
    if (!term) return;
    setSearching(true);
    setSearchError(null);
    // Clear previous results for a clean UI while loading
    setSearchResults([]);
    setSearchTotal(null);
    try {
      const url = `https://iblog.bz/search/?q=${encodeURIComponent(
        term
      )}&ajax=1`;
      const res = await fetch(url, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const results: SearchResultItem[] = Array.isArray(data?.results)
        ? data.results.map((r: SearchResultItem) => ({
            ...r,
            link: normalizeUrl(r.link),
          }))
        : [];
      setSearchResults(dedupeResults(results));
      setSearchTotal(typeof data?.total === "number" ? data.total : null);
    } catch (_err: unknown) {
      setSearchError("Failed to fetch results. Please try again.");
      setSearchResults([]);
      setSearchTotal(null);
    } finally {
      setSearching(false);
    }
  };

  return (
    <>
      <header className="sticky top-0 z-50 w-full border-b bg-[#3a3f47] backdrop-blur">
        <div className="mx-auto flex h-20 max-w-[1920px] items-center px-4 gap-4">
          {/* Logo */}
          <div className="flex-shrink-0">
            <button onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} className="cursor-pointer">
              <img src={ionLogo} alt="ION Logo" className="h-14 w-auto md:h-20 mt-[5px]" />
            </button>
          </div>

          {/* Desktop Navigation XL - 6 items (xl and up) */}
          <nav className="hidden xl:flex items-center gap-2 flex-1 justify-center">
            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Networks
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONNetworksMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Local
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span>ITIATIVES
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONInitiativesMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  SHOP <span className="text-primary group-hover:text-white">ION</span>
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONShopsMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase text-gray-400 hover:text-white">
                  CONNECT.<span className="text-primary group-hover:text-white">IONS</span>
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONConnectionsMenu />
              </PopoverContent>
            </Popover>

             <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase text-gray-400 hover:text-white">
              PressPass<span className="text-primary group-hover:text-white">.ION</span>
            </Button>
          </nav>


          {/* Large Tablet Navigation - 5 items (lg to xl) */}
          <nav className="hidden lg:xl:hidden lg:flex items-center gap-2 flex-1 justify-center">
            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Networks
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONNetworksMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Local
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span>ITIATIVES
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONInitiativesMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  SHOP <span className="text-primary group-hover:text-white">ION</span>
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONShopsMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase text-gray-400 hover:text-white">
                  CONNECT.<span className="text-primary group-hover:text-white">IONS</span>
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONConnectionsMenu />
              </PopoverContent>
            </Popover>
          </nav>

          {/* Tablet Navigation - 4 items (md to lg) */}
          <nav className="hidden md:lg:hidden md:flex items-center gap-2 flex-1 justify-center">
            <Popover>
              <PopoverTrigger asChild>
                <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Networks
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONNetworksMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span> Local
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  <span className="text-primary group-hover:text-white">ION</span>ITIATIVES
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONInitiativesMenu />
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                 <Button variant="ghost" className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white">
                  SHOP <span className="text-primary group-hover:text-white">ION</span>
                </Button>
              </PopoverTrigger>
              <PopoverPrimitive.Anchor asChild>
                <div className="fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0 pointer-events-none" />
              </PopoverPrimitive.Anchor>
              <PopoverContent
                className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover z-[60]"
                align="center"
                side="bottom"
                sideOffset={-20}
                onOpenAutoFocus={(e) => e.preventDefault()}
                onCloseAutoFocus={(e) => e.preventDefault()}
              >
                <IONShopsMenu />
              </PopoverContent>
            </Popover>
          </nav>

          {/* Right Side Actions */}
          <div className="flex items-center gap-1 md:gap-2 flex-shrink-0 ml-auto">
            {/* Search - Icon on mobile/tablet, full button on desktop xl+ */}
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setSearchOpen(true)}
              className="xl:hidden h-9 w-9 text-gray-400 hover:text-white"
              title="Search ION"
            >
              <Search className="h-4 w-4" />
              <span className="sr-only">Search ION</span>
            </Button>
            <Button
              variant="ghost"
              onClick={() => setSearchOpen(true)}
              className="hidden xl:flex items-center gap-2 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white"
            >
              <Search className="h-5 w-5" />
              Search ION
            </Button>

            {/* Upload - Icon on mobile/tablet, full button on desktop xl+ */}
            {headerButtons.upload.visible && (
              <>
                <Button
                  variant="default"
                  size="icon"
                  className="xl:hidden h-9 w-9 bg-primary text-primary-foreground hover:bg-primary/90 rounded-md"
                  title={headerButtons.upload.label}
                  onClick={() => window.location.href = isLoggedIn ? 'https://ions.com/app/creators.php' : 'https://ions.com/uploader/'}
                >
                  <Upload className="h-4 w-4" />
                  <span className="sr-only">{headerButtons.upload.label}</span>
                </Button>
                <Button
                  variant="default"
                  className="hidden xl:flex items-center gap-2 font-bebas text-xl uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90 rounded-md"
                  onClick={() => window.location.href = isLoggedIn ? 'https://ions.com/app/creators.php' : 'https://ions.com/uploader/'}
                >
                  <Upload className="h-5 w-5" />
                  {headerButtons.upload.label}
                </Button>
              </>
            )}

            {/* Sign ION - Only visible when not logged in */}
            {headerButtons.signIn.visible && !isLoggedIn && (
              <Button
                variant="outline"
                size="sm"
                className="group gap-0 font-bebas text-lg uppercase tracking-wider border-primary text-primary hover:bg-primary hover:text-white rounded-md"
                onClick={handleSignIn}
              >
                Sign<span className="text-primary group-hover:text-white">ION</span>
              </Button>
            )}

            {/* Notifications - Only visible when logged in and has notifications */}
            {isLoggedIn && unreadCount > 0 && (
              <Button
                variant="ghost"
                size="icon"
                className="relative h-9 w-9 text-gray-400 hover:text-white"
                title="Notifications"
                onClick={() => setNotificationsOpen(true)}
              >
                <Bell className="h-4 w-4" />
                <Badge 
                  className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-[10px] bg-primary text-primary-foreground rounded-full"
                >
                  {unreadCount}
                </Badge>
                <span className="sr-only">{unreadCount} unread notifications</span>
              </Button>
            )}

            {/* User Profile - Only visible when logged in */}
            <UserProfile onLogout={handleLogout} />

            {/* Theme Toggle - Only visible on xl+ (replaces hamburger menu) */}
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              className="hidden xl:flex h-9 w-9 text-gray-400 hover:text-white"
              title="Toggle theme"
            >
              {theme === "dark" ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
              <span className="sr-only">Toggle theme</span>
            </Button>

            {/* Hamburger Menu - Visible on md and below (hidden on xl+) */}
            <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
              <SheetTrigger asChild className="xl:hidden">
                <Button variant="ghost" size="icon" className="h-9 w-9 text-gray-400 hover:text-white">
                  <Menu className="h-5 w-5" />
                  <span className="sr-only">Menu</span>
                </Button>
              </SheetTrigger>
              <SheetContent side="right" className="w-[300px] overflow-y-auto">
                <div className="flex flex-col gap-3 py-3">
                  {/* Compact Top Row: Buttons + User Profile */}
                  <div className="flex items-center gap-2 px-3">
                    {headerButtons.upload.visible && (
                      <Button 
                        variant="default" 
                        size="sm"
                        className="flex-1 justify-center font-bebas uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90 rounded-md" 
                        onClick={() => {
                          window.location.href = isLoggedIn ? 'https://ions.com/app/creators.php' : 'https://ions.com/uploader/';
                          setMobileMenuOpen(false);
                        }}
                      >
                        <Upload className="mr-2 h-4 w-4" />
                        {headerButtons.upload.label}
                      </Button>
                    )}
                    {headerButtons.signIn.visible && !isLoggedIn && (
                      <Button 
                        variant="outline" 
                        size="sm"
                        className="flex-1 group gap-0 justify-center font-bebas uppercase tracking-wider border-primary text-primary hover:bg-primary hover:text-white rounded-md" 
                        onClick={() => {
                          handleSignIn();
                          setMobileMenuOpen(false);
                        }}
                      >
                        Sign<span className="text-primary group-hover:text-white">ION</span>
                      </Button>
                    )}
                    {isLoggedIn && (
                      <div className="flex items-center gap-2 ml-auto">
                        {unreadCount > 0 && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="relative h-8 w-8 text-gray-400 hover:text-white"
                            title="Notifications"
                            onClick={() => {
                              setNotificationsOpen(true);
                              setMobileMenuOpen(false);
                            }}
                          >
                            <Bell className="h-4 w-4" />
                            <Badge 
                              className="absolute -top-1 -right-1 h-4 w-4 flex items-center justify-center p-0 text-[9px] bg-primary text-primary-foreground rounded-full"
                            >
                              {unreadCount}
                            </Badge>
                          </Button>
                        )}
                        <UserProfile onLogout={handleLogout} />
                      </div>
                    )}
                  </div>

                  {/* Search Bar */}
                  <div className="flex items-center gap-2 px-3 py-2 border rounded-md bg-background/50 mx-3">
                    <Search className="h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="SEARCH ION"
                      className="border-0 bg-transparent p-0 h-auto font-bebas uppercase placeholder:text-muted-foreground focus-visible:ring-0 focus-visible:ring-offset-0"
                      onClick={() => {
                        setSearchOpen(true);
                        setMobileMenuOpen(false);
                      }}
                      readOnly
                    />
                  </div>

                  <div className="my-1 border-t" />

                  {/* Navigation Links */}
                  <div className="flex flex-col">
                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => {
                        setIonNetworksOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span> Networks
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => {
                        setIonLocalOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span> Local
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => {
                        setIonInitiativesOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span>ITIATIVES
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => {
                        setIonMallOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      SHOP <span className="text-primary group-hover:text-white">ION</span>
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => {
                        setConnectionsOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      CONNECT.<span className="text-primary group-hover:text-white">IONS</span>
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      PressPass<span className="text-primary group-hover:text-white">.ION</span>
                    </Button>
                  </div>

                  <div className="my-1 border-t" />

                  {/* Theme Toggle */}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
                    className="justify-start text-gray-400 hover:text-white h-9"
                  >
                    <Sun className="mr-2 h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
                    <Moon className="absolute ml-2 h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
                    <span className="ml-6">Toggle theme</span>
                  </Button>
                </div>
              </SheetContent>
            </Sheet>
          </div>
        </div>
      </header>

      {/* Search Dialog */}
      <Dialog open={searchOpen} onOpenChange={(open) => {
        setSearchOpen(open);
        if (!open) setSearchQuery("");
      }}>
        <DialogContent className="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle className="font-bebas text-2xl uppercase tracking-wider">
              Search <span className="text-primary">ION</span>
            </DialogTitle>
            <DialogDescription>
              Search across all ION Local Channels
            </DialogDescription>
          </DialogHeader>
          <div className="flex items-center gap-2 pt-4">
            <Search className="h-5 w-5 text-muted-foreground flex-shrink-0" />
            <Input
              placeholder="Type your search here..."
              className="flex-1"
              autoFocus
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && searchQuery.trim()) {
                  runSearch();
                }
              }}
            />
            {searchQuery.trim() && (
              <Button
                onClick={runSearch}
                className="font-bebas uppercase tracking-wider"
              >
                {searching ? (
                  <span className="flex items-center gap-2">
                    <Loader2 className="h-4 w-4 animate-spin" /> Searching
                  </span>
                ) : (
                  "Search"
                )}
              </Button>
            )}
          </div>
          {/* Results area */}
          <div className="pt-4">
            {searchError && (
              <div className="text-sm text-red-500">{searchError}</div>
            )}
            {!searchError &&
              searchResults.length === 0 &&
              !searching &&
              searchTotal === null && (
                <div className="text-sm text-muted-foreground">
                  Enter a search term and press Enter.
                </div>
              )}
            {!searchError &&
              !searching &&
              searchTotal !== null &&
              searchResults.length === 0 && (
                <div className="text-sm text-muted-foreground">
                  No results found.
                </div>
              )}
            {searchTotal !== null && (
              <div className="flex items-center justify-between pb-2">
                <div className="text-sm text-muted-foreground">
                  {searchTotal} results found
                </div>
              </div>
            )}
            <div
              className={`transition-[max-height,opacity] duration-300 ease-in-out overflow-hidden ${
                searching || searchResults.length > 0
                  ? "opacity-100 max-h-[60vh]"
                  : "opacity-0 max-h-0"
              }`}
            >
              <ScrollArea className="h-[60vh] pr-2">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                  {searchResults.map((item, index) => (
                    <a
                      key={`${item.id || item.link || index}-${index}`}
                      href={item.link}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Card className="overflow-hidden bg-muted/30 hover:bg-muted/20 transition-colors">
                        <div className="relative aspect-video w-full overflow-hidden">
                          {item.thumbnail ? (
                            <img
                              src={item.thumbnail}
                              alt={item.title}
                              className="h-full w-full object-cover"
                              onError={(e) => {
                                const img = e.currentTarget as HTMLImageElement;
                                img.onerror = null;
                                img.src = "/placeholder.svg";
                              }}
                            />
                          ) : (
                            <div className="h-full w-full bg-muted" />
                          )}
                          {item.type && (
                            <span className="absolute right-2 top-2 rounded bg-primary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-foreground">
                              {item.type}
                            </span>
                          )}
                        </div>
                        <div className="p-4">
                          <div className="text-sm font-medium leading-snug line-clamp-2">
                            {item.title}
                          </div>
                          <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span className="truncate max-w-[60%]">
                              {item.source_domain || ""}
                            </span>
                            <span className="whitespace-nowrap">
                              {item.relative_date || ""}
                            </span>
                          </div>
                        </div>
                      </Card>
                    </a>
                  ))}
                  {searching && (
                    <div className="col-span-full flex items-center justify-center py-8 text-muted-foreground">
                      <Loader2 className="mr-2 h-5 w-5 animate-spin" />{" "}
                      Searching...
                    </div>
                  )}
                </div>
              </ScrollArea>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* ION Local Menu Dialog */}
      <Dialog open={ionLocalOpen} onOpenChange={setIonLocalOpen}>
        <DialogContent className="max-w-[960px] p-0">
          <IONMenu />
        </DialogContent>
      </Dialog>

      {/* ION Networks Menu Dialog */}
      <Dialog open={ionNetworksOpen} onOpenChange={setIonNetworksOpen}>
        <DialogContent className="max-w-[960px] p-0">
          <IONNetworksMenu />
        </DialogContent>
      </Dialog>

      {/* ION Initiatives Menu Dialog */}
      <Dialog open={ionInitiativesOpen} onOpenChange={setIonInitiativesOpen}>
        <DialogContent className="max-w-[960px] p-0">
          <IONInitiativesMenu />
        </DialogContent>
      </Dialog>

      {/* ION Mall Menu Dialog */}
      <Dialog open={ionMallOpen} onOpenChange={setIonMallOpen}>
        <DialogContent className="max-w-[960px] p-0">
          <IONShopsMenu />
        </DialogContent>
      </Dialog>

      {/* Connections Menu Dialog */}
      <Dialog open={connectionsOpen} onOpenChange={setConnectionsOpen}>
        <DialogContent className="max-w-[960px] p-0">
          <IONConnectionsMenu />
        </DialogContent>
      </Dialog>

      {/* Notifications Panel */}
      <NotificationsPanel 
        open={notificationsOpen} 
        onOpenChange={setNotificationsOpen}
      />
    </>
  );
};

export default Header;
