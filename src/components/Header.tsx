import { useAuth } from "@/contexts/AuthContext";
import { Search, Upload, Moon, Sun, Menu, Bell, X } from "lucide-react";
import { useTheme } from "next-themes";
import { useNavigate } from "react-router-dom";
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

// Component mapping for menu items
const componentMap: Record<string, React.ComponentType<{ onClose?: () => void }>> = {
  IONNetworksMenu,
  IONInitiativesMenu,
  IONShopsMenu,
  IONSportsMenu,
  IONMenu,
  IONConnectionsMenu,
};

const Header = () => {
  const { isLoggedIn, user, logout, login } = useAuth();
  const navigate = useNavigate();
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  
  // Dynamic dialog states for mobile menus
  const [dialogStates, setDialogStates] = useState<Record<string, boolean>>({});
  
  // Dynamic popover states for desktop hover menus
  const [popoverStates, setPopoverStates] = useState<Record<string, boolean>>({});
  
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  
  const { theme, setTheme } = useTheme();
  const { isMd, isLg, isXl } = useBreakpoint();
  
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
          <MenuComponent onClose={() => setPopoverStates(prev => ({ ...prev, [item.id]: false }))} />
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
        <div className="mx-auto flex h-20 max-w-[1920px] items-center px-4 gap-4">
          {/* Logo */}
          <div className="flex-shrink-0">
            <a href="https://ions.com" className="cursor-pointer block">
              <img src={ionLogo} alt="ION Logo" className="h-14 w-auto md:h-20 mt-[5px]" />
            </a>
          </div>

          {/* Desktop Navigation XL - Show all items */}
          {isXl && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.map(item => renderDesktopMenuItem(item))}
            </nav>
          )}

          {/* Desktop Navigation LG - Show first 6 items */}
          {isLg && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.slice(0, 6).map(item => renderDesktopMenuItem(item))}
            </nav>
          )}

          {/* Desktop Navigation MD - Show first 5 items */}
          {isMd && (
            <nav className="flex relative items-center gap-2 flex-1 justify-center">
              {visibleMenuItems.slice(0, 5).map(item => renderDesktopMenuItem(item))}
            </nav>
          )}

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
                    {visibleMenuItems.map(item => renderMobileMenuItem(item))}
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
        <DialogContent className="sm:max-w-[900px] max-w-[95vw] w-full p-6 sm:p-8 md:p-12 bg-background fixed left-[50%] top-[20%] translate-x-[-50%] translate-y-0 data-[state=open]:slide-in-from-top-[100px] data-[state=closed]:slide-out-to-top-[100px]">
          <DialogHeader className="mb-6 sm:mb-8">
            <DialogTitle className="font-bebas text-3xl sm:text-4xl md:text-5xl uppercase tracking-wider text-center text-foreground">
              Search <span className="text-primary">ION</span> Network
            </DialogTitle>
          </DialogHeader>
          <div className="relative w-full">
            <Input
              placeholder="Search videos, channels, content..."
              className="w-full h-12 sm:h-14 md:h-16 pr-28 sm:pr-32 md:pr-36 text-sm sm:text-base md:text-lg rounded-lg border-2 border-primary/50 bg-card/50 text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:border-primary"
              autoFocus
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const query = encodeURIComponent(searchQuery.trim());
                  if (query) {
                    navigate(`/search/?q=${query}`);
                    setSearchOpen(false);
                  }
                }
              }}
            />
            <Button
              onClick={() => {
                const query = encodeURIComponent(searchQuery.trim());
                if (query) {
                  navigate(`/search/?q=${query}`);
                  setSearchOpen(false);
                }
              }}
              className="absolute right-1 top-1/2 -translate-y-1/2 h-10 sm:h-12 md:h-14 px-4 sm:px-6 md:px-8 font-bebas text-sm sm:text-base md:text-lg uppercase tracking-wider rounded-md bg-primary hover:bg-primary/90 text-primary-foreground"
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
            <DialogContent className="max-w-[960px] p-0">
              <MenuComponent />
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
