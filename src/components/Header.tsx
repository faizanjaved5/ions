import { useAuth } from "@/contexts/AuthContext";
import { Search, Upload, Moon, Sun, Menu, Bell, X } from "lucide-react";
import { useTheme } from "next-themes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
import IONSportsMenu from "@/components/IONSportsMenu";
import IONConnectionsMenu from "@/components/IONConnectionsMenu";
import UserProfile from "@/components/UserProfile";
import NotificationsPanel from "@/components/NotificationsPanel";
import { useState } from "react";
import { useBreakpoint } from "@/hooks/use-breakpoint";
import headerMenuData from "@/data/headerMenuData.json";

// Component mapping for menu items (allow extra props like linkType)
type MenuComponentProps = {
  onClose?: () => void;
  linkType?: "router" | "anchor";
  spriteUrl?: string;
  externalTheme?: "dark" | "light";
  onExternalThemeToggle?: () => void;
};
const componentMap: Record<string, React.ComponentType<MenuComponentProps>> = {
  IONNetworksMenu,
  IONInitiativesMenu,
  IONShopsMenu,
  IONSportsMenu,
  IONMenu,
  IONConnectionsMenu,
};

interface HeaderProps {
  onSearch?: (query: string) => void;
  uploadUrl?: string;
  signInUrl?: string;
  signOutUrl?: string;
  linkType?: "router" | "anchor";
  disableThemeToggle?: boolean;
  spriteUrl?: string;
  externalTheme?: "dark" | "light";
  onExternalThemeToggle?: () => void;
}

const Header = ({ onSearch, uploadUrl, signInUrl, signOutUrl, linkType = "router", disableThemeToggle = false, spriteUrl, externalTheme, onExternalThemeToggle }: HeaderProps = {}) => {
  const { isLoggedIn, user, logout, login } = useAuth();
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  
  // Dynamic dialog states for mobile menus
  const [dialogStates, setDialogStates] = useState<Record<string, boolean>>({});
  
  // Dynamic popover states for desktop hover menus
  const [popoverStates, setPopoverStates] = useState<Record<string, boolean>>({});
  
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  
  const { theme, setTheme } = useTheme();
  const activeTheme = externalTheme || theme;
  const handleThemeToggle = () => {
    if (onExternalThemeToggle) {
      onExternalThemeToggle();
    } else {
      setTheme(theme === "dark" ? "light" : "dark");
    }
  };
  const { isMd, isLg, isXl } = useBreakpoint();
  const searchPlaceholder = isMd ? "Search videos, channels, content..." : "Type to search ION";
  
  // Default header buttons (these could also come from your JSON)
  const headerButtons = {
    upload: { label: "Upload", link: "/upload", visible: true },
    signIn: { label: "Sign In", link: "/signin", visible: true }
  };
  
  const notifications = user?.notifications || [];
  const unreadCount = notifications.filter(n => !n.read).length;

  const handleLogout = () => {
    // Update local state then redirect if provided
    logout();
    if (signOutUrl) {
      window.location.href = signOutUrl;
    }
  };

  const handleSignIn = () => {
    if (signInUrl) {
      window.location.href = signInUrl;
      return;
    }
    login();
  };

  // Get visible and sorted menu items
  const visibleMenuItems = headerMenuData.menuItems
    .filter(item => item.visible)
    .sort((a, b) => a.order - b.order);

  // Helper to render menu button label
  const renderMenuLabel = (item: typeof headerMenuData.menuItems[0]) => {
    const { labelParts } = item;
    if (labelParts.reverseOrder) {
      return (
        <>
          {labelParts.secondary}
          <span className="text-primary group-hover:text-white">{labelParts.primary}</span>
        </>
      );
    }
    return (
      <>
        <span className="text-primary group-hover:text-white">{labelParts.primary}</span>
        {labelParts.secondary}
      </>
    );
  };

  // Helper to render desktop popover menu
  const renderDesktopMenuItem = (item: typeof headerMenuData.menuItems[0]) => {
    const MenuComponent = item.component ? componentMap[item.component] : null;
    
    if (!MenuComponent) {
      // No component means it's a simple link button (like PressPass)
      return (
        <Button 
          key={item.id}
          variant="ghost" 
          className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white"
        >
          {renderMenuLabel(item)}
        </Button>
      );
    }

    return (
      <Popover 
        key={item.id}
        open={popoverStates[item.id] || false} 
        onOpenChange={(open) => setPopoverStates(prev => ({ ...prev, [item.id]: open }))}
      >
        <PopoverTrigger asChild>
          <Button 
            variant="ghost" 
            className="group gap-0 font-bebas text-xl uppercase tracking-wider text-gray-400 hover:text-white"
          >
            {renderMenuLabel(item)}
          </Button>
        </PopoverTrigger>
        <PopoverPrimitive.Anchor className="pointer-events-none fixed left-1/2 top-20 -translate-x-1/2 h-0 w-0" />
        <PopoverContent
          className="p-0 border-0 shadow-none w-[calc(100vw-2rem)] max-w-[960px] bg-popover"
          align="center"
          side="bottom"
          sideOffset={4}
          onOpenAutoFocus={(e) => e.preventDefault()}
          onCloseAutoFocus={(e) => e.preventDefault()}
        >
          <MenuComponent
            onClose={() => setPopoverStates(prev => ({ ...prev, [item.id]: false }))}
            linkType={linkType}
            spriteUrl={spriteUrl}
            externalTheme={activeTheme as "dark" | "light"}
            onExternalThemeToggle={handleThemeToggle}
          />
        </PopoverContent>
      </Popover>
    );
  };

  // Helper to render mobile menu button
  const renderMobileMenuItem = (item: typeof headerMenuData.menuItems[0]) => {
    const MenuComponent = item.component ? componentMap[item.component] : null;
    
    return (
      <Button
        key={item.id}
        variant="ghost"
        className="group gap-0 justify-start font-bebas text-lg uppercase w-full h-9"
        onClick={() => {
          if (MenuComponent) {
            setDialogStates(prev => ({ ...prev, [item.id]: true }));
          }
          setMobileMenuOpen(false);
        }}
      >
        {renderMenuLabel(item)}
      </Button>
    );
  };

  return (
    <>
      <header className="sticky top-0 z-50 w-full border-b bg-[#3a3f47] backdrop-blur">
        <div className="mx-auto flex h-20 max-w-[1920px] items-center px-4 gap-2 md:gap-4">
          {/* Logo */}
          <div className="flex-shrink-0">
            <a href="https://ions.com" className="cursor-pointer block">
              <img src={ionLogo} alt="ION Logo" className="h-20 w-auto md:h-20 mt-[5px]" />
            </a>
          </div>

          {/* Mobile Search Button - Centered */}
          <Button
            variant="ghost"
            onClick={() => setSearchOpen(true)}
            className="md:hidden flex items-center gap-2 mx-auto flex-shrink-0 text-primary hover:text-white hover:bg-primary/20 rounded-md px-3 py-2"
            title="Search ION"
          >
            <Search className="h-4 w-4" />
            <span className="text-xs uppercase tracking-wider">Search</span>
          </Button>

          {/* Desktop Search Button */}
          <Button
            variant="ghost"
            onClick={() => setSearchOpen(true)}
            className="hidden md:flex items-center gap-2 font-bebas text-xl uppercase tracking-wider group flex-shrink-0 border border-primary/30 hover:border-primary rounded-md px-4 py-2 ml-12"
          >
            <Search className="h-5 w-5 text-primary group-hover:text-white" />
            <span className="text-gray-400 group-hover:text-white">Search</span>
            <span className="text-primary group-hover:text-white">ION</span>
          </Button>

          {/* Desktop Navigation - Progressive display based on available width */}
          {/* XL: Show all 7 items */}
          {isXl && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.map(item => renderDesktopMenuItem(item))}
            </nav>
          )}
          
          {/* LG: Show first 5 items */}
          {isLg && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.slice(0, 5).map(item => renderDesktopMenuItem(item))}
            </nav>
          )}
          
          {/* MD: Show first 3 items */}
          {isMd && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.slice(0, 3).map(item => renderDesktopMenuItem(item))}
            </nav>
          )}

          {/* Right Side Actions */}
          <div className="flex items-center gap-1 md:gap-2 flex-shrink-0 ml-auto">
            {/* Upload - Icon on mobile/tablet, full button on desktop xl+ */}
            {headerButtons.upload.visible && (
              <>
                <Button
                  variant="default"
                  size="icon"
                  className="xl:hidden h-10 w-10 bg-primary text-primary-foreground hover:bg-primary/90 rounded-md flex-shrink-0"
                  title={headerButtons.upload.label}
                  onClick={() => {
                    const url = uploadUrl || (isLoggedIn ? '/app/creators.php' : 'uploader/');
                    window.location.href = url;
                  }}
                >
                  <Upload className="h-5 w-5" />
                  <span className="sr-only">{headerButtons.upload.label}</span>
                </Button>
                <Button
                  variant="default"
                  className="hidden xl:flex items-center gap-2 font-bebas text-xl uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90 rounded-md"
                  onClick={() => {
                    const url = uploadUrl || (isLoggedIn ? '/app/creators.php' : 'uploader/');
                    window.location.href = url;
                  }}
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

            {/* Notifications - Only visible on md+ in header; on mobile it's in the drawer */}
            {isLoggedIn && unreadCount > 0 && (
              <Button
                variant="ghost"
                size="icon"
                className="hidden md:inline-flex relative h-10 w-10 text-primary hover:text-white hover:bg-primary/20 flex-shrink-0"
                title="Notifications"
                onClick={() => setNotificationsOpen(true)}
              >
                <Bell className="h-5 w-5" />
                <Badge 
                  className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-[10px] bg-primary text-primary-foreground rounded-full"
                >
                  {unreadCount}
                </Badge>
                <span className="sr-only">{unreadCount} unread notifications</span>
              </Button>
            )}

            {/* User Profile - Only visible when logged in */}
            <UserProfile onLogout={handleLogout} linkType={linkType} />

            {/* Theme Toggle - Only visible on xl+ (replaces hamburger menu) */}
            {!disableThemeToggle && (
              <Button
                variant="ghost"
                size="icon"
                onClick={handleThemeToggle}
                className="hidden xl:flex h-10 w-10 text-primary hover:text-white hover:bg-primary/20"
                title="Toggle theme"
              >
                {activeTheme === "dark" ? (
                  <Sun className="h-5 w-5" />
                ) : (
                  <Moon className="h-5 w-5" />
                )}
                <span className="sr-only">Toggle theme</span>
              </Button>
            )}

            {/* Hamburger Menu - Always visible on mobile/tablet (hidden only on xl+) */}
            <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="xl:hidden h-10 w-10 text-primary hover:text-white hover:bg-primary/20 flex-shrink-0">
                  <Menu className="h-6 w-6" />
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
                          const url = uploadUrl || (isLoggedIn ? '/app/creators.php' : 'uploader/');
                          window.location.href = url;
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
                  <Button
                    variant="ghost"
                    onClick={() => {
                      setSearchOpen(true);
                      setMobileMenuOpen(false);
                    }}
                    className="flex items-center gap-2 font-bebas text-xl uppercase tracking-wider group mx-3 justify-start border border-primary/30 hover:border-primary rounded-md px-4 py-2 h-auto"
                  >
                    <Search className="h-5 w-5 text-primary group-hover:text-white flex-shrink-0" />
                    <span className="text-gray-400 group-hover:text-white">Search</span>
                    <span className="text-primary group-hover:text-white">ION</span>
                  </Button>

                  {/* Navigation Links */}
                  <div className="flex flex-col">
                    {visibleMenuItems.map(item => renderMobileMenuItem(item))}
                  </div>

                  <div className="my-1 border-t" />

                  {/* Theme Toggle */}
                  {!disableThemeToggle && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={handleThemeToggle}
                      className="justify-start text-gray-400 hover:text-white h-9"
                    >
                      <Sun className="mr-2 h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
                      <Moon className="absolute ml-2 h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
                      <span className="ml-6">Toggle theme</span>
                    </Button>
                  )}
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
        <DialogContent className="sm:max-w-[900px] max-w-[90vw] w-full p-4 sm:p-8 md:p-12 bg-background fixed left-[50%] top-[18%] translate-x-[-50%] translate-y-0 overflow-y-auto rounded-md sm:rounded-lg max-h-[85vh] data-[state=open]:slide-in-from-top-[100px] data-[state=closed]:slide-out-to-top-[100px]"> 
          <DialogHeader className="mb-4 sm:mb-8"> 
            <DialogTitle className="text-center text-foreground"> 
              {/* Mobile: Simple text "Search ION Network" */}
              <span className="font-bebas text-2xl uppercase tracking-wider block sm:hidden">
                Search <span className="text-primary">ION</span> Network
              </span>
              {/* Desktop: Logo version */}
              <span className="hidden sm:flex font-bebas text-3xl sm:text-4xl md:text-5xl uppercase tracking-wider items-center justify-center gap-3"> 
                Search 
                <img src={ionLogo} alt="ION" className="h-12 sm:h-16 md:h-20 inline-block" /> 
                Network 
              </span> 
            </DialogTitle> 
          </DialogHeader>
          <div className="flex flex-col sm:relative gap-3 sm:gap-0 w-full">
            <Input
              placeholder={searchPlaceholder}
              className="w-full h-14 md:h-16 sm:pr-28 md:pr-32 lg:pr-36 text-base md:text-xl lg:text-2xl rounded-lg border-2 border-primary bg-card/50 text-foreground placeholder:text-muted-foreground focus:bg-card focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:border-primary focus-visible:outline-none transition-colors"
              autoFocus
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const query = encodeURIComponent(searchQuery.trim());
                  if (query) {
                    if (onSearch) {
                      onSearch(query);
                    } else {
                      window.location.href = `/search/?q=${query}`;
                    }
                    setSearchOpen(false);
                  }
                }
              }}
            />
            <Button
              onClick={() => {
                const query = encodeURIComponent(searchQuery.trim());
                if (query) {
                  if (onSearch) {
                    onSearch(query);
                  } else {
                    window.location.href = `/search/?q=${query}`;
                  }
                  setSearchOpen(false);
                }
              }}
              className="w-full h-12 sm:w-auto sm:absolute sm:right-1 sm:top-1/2 sm:-translate-y-1/2 sm:h-12 md:h-14 px-4 sm:px-6 md:px-8 font-bebas text-base md:text-lg uppercase tracking-wider rounded-lg sm:rounded-md bg-primary hover:bg-primary/90 text-primary-foreground"
            >
              Search
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Dynamic Menu Dialogs for Mobile */}
      {visibleMenuItems.map(item => {
        const MenuComponent = item.component ? componentMap[item.component] : null;
        if (!MenuComponent) return null;
        
        return (
          <Dialog 
            key={item.id}
            open={dialogStates[item.id] || false} 
            onOpenChange={(open) => setDialogStates(prev => ({ ...prev, [item.id]: open }))}
          >
            <DialogContent className="max-w-[95vw] sm:max-w-[960px] p-0 h-[95vh] sm:h-auto sm:max-h-[90vh] overflow-hidden [&>button]:hidden top-[2.5vh] sm:top-[50%] translate-y-0 sm:-translate-y-1/2">
              <MenuComponent
                onClose={() => setDialogStates(prev => ({ ...prev, [item.id]: false }))}
                linkType={linkType}
                spriteUrl={spriteUrl}
                externalTheme={activeTheme as "dark" | "light"}
                onExternalThemeToggle={handleThemeToggle}
              />
            </DialogContent>
          </Dialog>
        );
      })}

      {/* Notifications Panel */}
      <NotificationsPanel 
        open={notificationsOpen} 
        onOpenChange={setNotificationsOpen}
      />
    </>
  );
};

export default Header;
