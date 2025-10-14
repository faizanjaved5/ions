import { useState } from "react";
import { Search, Upload, Moon, Sun, Menu } from "lucide-react";
import { useTheme } from "next-themes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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

const Header = () => {
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [ionLocalOpen, setIonLocalOpen] = useState(false);
  const [ionNetworksOpen, setIonNetworksOpen] = useState(false);
  const [ionInitiativesOpen, setIonInitiativesOpen] = useState(false);
  const [ionMallOpen, setIonMallOpen] = useState(false);
  const [connectionsOpen, setConnectionsOpen] = useState(false);
  const { theme, setTheme } = useTheme();

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
                  <span className="text-primary group-hover:text-white">ION</span> Mall
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
                  CONNECT<span className="text-primary group-hover:text-white">.ION</span>S
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
                  <span className="text-primary group-hover:text-white">ION</span> Mall
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
                  CONNECT<span className="text-primary group-hover:text-white">.ION</span>S
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
                  <span className="text-primary group-hover:text-white">ION</span> Mall
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
          <div className="flex items-center gap-1 md:gap-2 flex-shrink-0">
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
            <Button
              variant="default"
              size="icon"
              className="xl:hidden h-9 w-9 bg-primary text-primary-foreground hover:bg-primary/90"
              title="Upload"
            >
              <Upload className="h-4 w-4" />
              <span className="sr-only">Upload</span>
            </Button>
            <Button
              variant="default"
              className="hidden xl:flex items-center gap-2 font-bebas text-xl uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90"
            >
              <Upload className="h-5 w-5" />
              Upload
            </Button>

            {/* Sign ION - Always visible */}
            <Button
              variant="outline"
              size="sm"
              className="group gap-0 font-bebas text-lg uppercase tracking-wider border-primary text-primary hover:bg-primary hover:text-white"
            >
              Sign<span className="text-primary group-hover:text-white">ION</span>
            </Button>

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
                <div className="flex flex-col gap-4 py-4">
                  {/* Search Bar at Top */}
                  <div className="flex items-center gap-2 px-3 py-2 border rounded-md bg-background/50">
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

                  {/* Upload and SignION side by side */}
                  <div className="grid grid-cols-2 gap-2">
                    <Button 
                      variant="default" 
                      size="sm"
                      className="w-full justify-center font-bebas uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90" 
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      <Upload className="mr-2 h-4 w-4" />
                      Upload
                    </Button>
                    <Button 
                      variant="outline" 
                      size="sm"
                      className="w-full group gap-0 justify-center font-bebas uppercase tracking-wider border-primary text-primary hover:bg-primary hover:text-white" 
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Sign<span className="text-primary group-hover:text-white">ION</span>
                    </Button>
                  </div>

                  <div className="my-2 border-t" />

                  {/* Navigation Links */}
                  <div className="flex flex-col gap-1">
                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-xl uppercase w-full"
                      onClick={() => {
                        setIonLocalOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span> Local
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-xl uppercase w-full"
                      onClick={() => {
                        setIonNetworksOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span> Networks
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-xl uppercase w-full"
                      onClick={() => {
                        setIonInitiativesOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span>ITIATIVES
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-xl uppercase w-full"
                      onClick={() => {
                        setIonMallOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      <span className="text-primary group-hover:text-white">ION</span> Mall
                    </Button>

                    <Button
                      variant="ghost"
                      className="group gap-0 justify-start font-bebas text-xl uppercase w-full"
                      onClick={() => {
                        setConnectionsOpen(true);
                        setMobileMenuOpen(false);
                      }}
                    >
                      CONNECT<span className="text-primary group-hover:text-white">.ION</span>S
                    </Button>
                  </div>

                  <Button
                    variant="ghost"
                    className="group gap-0 justify-start font-bebas text-lg uppercase w-full"
                    onClick={() => setMobileMenuOpen(false)}
                  >
                    PressPass<span className="text-primary group-hover:text-white">.ION</span>
                  </Button>

                  <div className="my-2 border-t" />

                  {/* Theme Toggle */}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
                    className="justify-start text-gray-400 hover:text-white"
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
                if (e.key === 'Enter' && searchQuery.trim()) {
                  console.log('Search for:', searchQuery);
                }
              }}
            />
            {searchQuery.trim() && (
              <Button
                onClick={() => {
                  console.log('Search for:', searchQuery);
                }}
                className="font-bebas uppercase tracking-wider"
              >
                Search
              </Button>
            )}
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
    </>
  );
};

export default Header;
