import { useState, useMemo } from "react";
import { Search, MapPin, ChevronRight, ExternalLink, Sun, Moon, ChevronLeft, Menu } from "lucide-react";
import { useTheme } from "next-themes";
import menuData from "../data/menuData.json";
import { Button } from "./ui/button";
import { Sheet, SheetContent, SheetTrigger } from "./ui/sheet";
import { useIsMobile } from "@/hooks/use-mobile";

interface MenuItem {
  id: string;
  name: string;
  flag?: string;
  states?: Array<{ name: string; url: string }>;
  cities?: Array<{ name: string; url: string }>;
  countries?: any[];
}

// Helper function to generate URL from name
const generateUrl = (name: string): string => {
  return `https://ions.com/ion-${name.toLowerCase().replace(/\s+/g, '-')}`;
};

// State/Province code mapping for North America
const stateCodeMap: Record<string, string> = {
  // US States
  "alabama": "AL", "alaska": "AK", "arizona": "AZ", "arkansas": "AR",
  "california": "CA", "colorado": "CO", "connecticut": "CT", "delaware": "DE",
  "florida": "FL", "georgia": "GA", "hawaii": "HI", "idaho": "ID",
  "illinois": "IL", "indiana": "IN", "iowa": "IA", "kansas": "KS",
  "kentucky": "KY", "louisiana": "LA", "maine": "ME", "maryland": "MD",
  "massachusetts": "MA", "michigan": "MI", "minnesota": "MN", "mississippi": "MS",
  "missouri": "MO", "montana": "MT", "nebraska": "NE", "nevada": "NV",
  "new hampshire": "NH", "new jersey": "NJ", "new york": "NY", "north carolina": "NC",
  "north dakota": "ND", "ohio": "OH", "oklahoma": "OK", "oregon": "OR",
  "pennsylvania": "PA", "rhode island": "RI", "south carolina": "SC", "south dakota": "SD",
  "tennessee": "TN", "texas": "TX", "utah": "UT", "vermont": "VT",
  "virginia": "VA", "washington": "WA", "washington dc": "DC", "west virginia": "WV",
  "wisconsin": "WI", "wyoming": "WY",
  // Canadian Provinces
  "alberta": "AB", "british columbia": "BC", "manitoba": "MB", "new brunswick": "NB",
  "newfoundland and labrador": "NL", "northwest territories": "NT", "nova scotia": "NS",
  "nunavut": "NU", "ontario": "ON", "prince edward island": "PE", "quebec": "QC",
  "saskatchewan": "SK", "yukon": "YT",
  // Mexican States
  "aguascalientes": "AGS", "baja california": "BC", "baja california sur": "BCS",
  "campeche": "CAMP", "chiapas": "CHIS", "chihuahua": "CHIH", "coahuila": "COAH",
  "colima": "COL", "durango": "DGO", "guanajuato": "GTO", "guerrero": "GRO",
  "hidalgo": "HGO", "jalisco": "JAL", "mexico city": "CDMX", "michoacán": "MICH",
  "morelos": "MOR", "nayarit": "NAY", "nuevo león": "NL", "oaxaca": "OAX",
  "puebla": "PUE", "querétaro": "QRO", "quintana roo": "QROO", "san luis potosí": "SLP",
  "sinaloa": "SIN", "sonora": "SON", "tabasco": "TAB", "tamaulipas": "TAMPS",
  "tlaxcala": "TLAX", "veracruz": "VER", "yucatán": "YUC", "zacatecas": "ZAC"
};

// Country ID to flag code mapping
const countryCodeMap: Record<string, string> = {
  "usa": "us",
  "canada": "ca",
  "mexico": "mx",
  "belize": "bz",
  "costa-rica": "cr",
  "el-salvador": "sv",
  "guatemala": "gt",
  "honduras": "hn",
  "nicaragua": "ni",
  "panama": "pa",
  "argentina": "ar",
  "bolivia": "bo",
  "brazil": "br",
  "chile": "cl",
  "colombia": "co",
  "ecuador": "ec",
  "guyana": "gy",
  "paraguay": "py",
  "peru": "pe",
  "suriname": "sr",
  "uruguay": "uy",
  "venezuela": "ve",
  "albania": "al",
  "andorra": "ad",
  "austria": "at",
  "belgium": "be",
  "bosnia": "ba",
  "bulgaria": "bg",
  "croatia": "hr",
  "czech-republic": "cz",
  "denmark": "dk",
  "england": "gb",
  "estonia": "ee",
  "finland": "fi",
  "france": "fr",
  "germany": "de",
  "greece": "gr",
  "hungary": "hu",
  "iceland": "is",
  "ireland": "ie",
  "italy": "it",
  "latvia": "lv",
  "lithuania": "lt",
  "luxembourg": "lu",
  "malta": "mt",
  "montenegro": "me",
  "netherlands": "nl",
  "norway": "no",
  "poland": "pl",
  "portugal": "pt",
  "romania": "ro",
  "scotland": "gb-sct",
  "serbia": "rs",
  "slovakia": "sk",
  "slovenia": "si",
  "spain": "es",
  "sweden": "se",
  "switzerland": "ch",
  "wales": "gb-wls",
  "antigua": "ag",
  "bahamas": "bs",
  "barbados": "bb",
  "cuba": "cu",
  "dominica": "dm",
  "dominican-republic": "do",
  "grenada": "gd",
  "haiti": "ht",
  "jamaica": "jm",
  "saint-kitts": "kn",
  "saint-lucia": "lc",
  "saint-vincent": "vc",
  "trinidad": "tt",
  "fiji": "fj",
  "kiribati": "ki",
  "marshall-islands": "mh",
  "micronesia-country": "fm",
  "nauru": "nr",
  "palau": "pw",
  "papua": "pg",
  "samoa": "ws",
  "solomon": "sb",
  "tonga": "to",
  "tuvalu": "tv",
  "vanuatu": "vu",
  "australia": "au",
  "new-zealand": "nz",
  "china": "cn",
  "india": "in",
  "japan": "jp",
  "south-korea": "kr",
  "russia": "ru",
  "ukraine": "ua",
  "turkey": "tr",
  "uae": "ae",
  "saudi-arabia": "sa",
  "israel": "il",
  "egypt": "eg",
  "south-africa": "za",
  "nigeria": "ng",
  "kenya": "ke",
  "morocco": "ma",
  "thailand": "th",
  "vietnam": "vn",
  "indonesia": "id",
  "malaysia": "my",
  "singapore": "sg",
  "philippines": "ph",
  "pakistan": "pk",
  "bangladesh": "bd",
  "iran": "ir",
  "iraq": "iq",
  "qatar": "qa",
  "kuwait": "kw",
  "lebanon": "lb"
};

export const IONMenu = () => {
  const { theme, setTheme } = useTheme();
  const [selectedRegion, setSelectedRegion] = useState<string | null>("featured");
  const [selectedCountry, setSelectedCountry] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [useBebasFont, setUseBebasFont] = useState(false);
  const [mobileView, setMobileView] = useState<"regions" | "countries" | "states">("regions");
  const [isOpen, setIsOpen] = useState(false);
  const isMobile = useIsMobile();
  const isDarkMode = theme === "dark";

  const currentRegion = useMemo(() => {
    return menuData.regions.find(r => r.id === selectedRegion);
  }, [selectedRegion]);

  const currentCountry = useMemo(() => {
    if (!currentRegion || !selectedCountry) return null;
    return currentRegion.countries?.find((c: MenuItem) => c.id === selectedCountry);
  }, [currentRegion, selectedCountry]);

  const filteredItems = useMemo(() => {
    if (!searchQuery.trim()) return null;

    const query = searchQuery.toLowerCase();
    const results: any[] = [];

    // Search through all regions, countries, and states/cities
    menuData.regions.forEach(region => {
      if (region.name.toLowerCase().includes(query)) {
        results.push({ type: "region", ...region });
      }
      region.countries?.forEach((country: MenuItem) => {
        const countryCode = countryCodeMap[country.id] || country.id;
        const matchesName = country.name.toLowerCase().includes(query);
        const matchesCode = countryCode.toLowerCase().includes(query);
        
        if (matchesName || matchesCode) {
          results.push({ type: "country", ...country, regionId: region.id });
        }
        country.states?.forEach(state => {
          const stateCode = stateCodeMap[state.name.toLowerCase()] || "";
          const matchesName = state.name.toLowerCase().includes(query);
          const matchesCode = stateCode.toLowerCase().includes(query);
          
          if (matchesName || matchesCode) {
            results.push({ type: "state", ...state, countryId: country.id, regionId: region.id });
          }
        });
        country.cities?.forEach(city => {
          if (city.name.toLowerCase().includes(query)) {
            results.push({ type: "city", ...city, countryId: country.id, regionId: region.id });
          }
        });
      });
    });

    // Search featured channels
    menuData.featuredChannels.forEach(channel => {
      if (channel.name.toLowerCase().includes(query)) {
        results.push({ type: "featured", ...channel });
      }
    });

    return results;
  }, [searchQuery]);

  const handleRegionClick = (regionId: string) => {
    setSelectedRegion(regionId);
    setSelectedCountry(null);
    setSearchQuery("");
    if (isMobile && regionId !== "featured") {
      setMobileView("countries");
    }
  };

  const handleCountryClick = (countryId: string) => {
    setSelectedCountry(countryId);
    if (isMobile) {
      setMobileView("states");
    }
  };

  const handleMobileBack = () => {
    if (mobileView === "states") {
      setSelectedCountry(null);
      setMobileView("countries");
    } else if (mobileView === "countries") {
      setSelectedRegion(null);
      setMobileView("regions");
    }
  };

  const renderStatesGrid = (states: Array<{ name: string; url?: string }>) => {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem] w-full">
        {states.map((state) => {
          const stateUrl = state.url || generateUrl(state.name);
          const stateCode = stateCodeMap[state.name.toLowerCase()];
          return (
            <a
              key={state.name}
              href={stateUrl}
              className={`group flex items-center gap-2 px-3 py-2.5 text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] ${
                useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
              }`}
            >
              <MapPin className="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" />
              <span className="truncate flex-1">
                <span className="text-primary font-medium">ION</span>{" "}
                {state.name}
              </span>
              {stateCode && (
                <span className="text-muted-foreground/60 text-xs ml-auto flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                  ({stateCode})
                </span>
              )}
              <ExternalLink 
                className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" 
                onClick={(e) => {
                  e.preventDefault();
                  window.open(stateUrl, '_blank');
                }}
              />
            </a>
          );
        })}
      </div>
    );
  };

  const bebasStyles = useBebasFont ? 'font-bebas text-lg font-normal whitespace-nowrap uppercase tracking-wider' : '';
  const menuItemPadding = useBebasFont ? 'py-2' : 'py-3';

  const MenuContent = () => (
    <div 
      className={`w-full border border-menu-border rounded-sm overflow-hidden bg-menu-bg ${
        isMobile ? 'h-full border-0 rounded-none' : 'max-w-[960px]'
      }`}
    >
      {/* Header */}
      <div className="px-3 md:px-4 py-3 border-b border-menu-border">
        <div className="flex items-center justify-between gap-2 md:gap-4 mb-3 md:mb-0">
          <div className="flex items-center gap-2 md:gap-3 min-w-0">
            {isMobile && mobileView !== "regions" && (
              <Button
                variant="ghost"
                size="sm"
                onClick={handleMobileBack}
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
          <div className={`${isMobile ? 'hidden' : 'flex-1 max-w-md relative mx-4 md:mx-[30px]'}`}>
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Local Channels"
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
              <span className={useBebasFont ? 'font-bebas' : ''}>Aa</span>
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              className="h-8 w-8 p-0"
            >
              {isDarkMode ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </Button>
          </div>
        </div>
        {isMobile && (
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search ION Local Channels"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground"
            />
          </div>
        )}
      </div>

      {/* Main Content */}
      <div className={`flex ${isMobile ? 'h-[calc(100vh-80px)]' : 'h-[420px]'}`}>
        {/* Left Sidebar - Main Categories (Desktop) or Full View (Mobile) */}
        <div className={`flex flex-col border-menu-border overflow-y-auto overflow-x-hidden ${
          isMobile 
            ? 'w-full' 
            : 'w-48 md:w-60 border-r'
        } ${isMobile && mobileView !== "regions" ? 'hidden' : ''}`}>
          <button
            onClick={() => {
              setSelectedRegion("featured");
              setSelectedCountry(null);
              setSearchQuery("");
            }}
            className={`flex items-center justify-between px-4 ${menuItemPadding} text-left transition-all duration-200 border-b border-menu-border ${
              useBebasFont ? bebasStyles : 'text-sm uppercase tracking-wide'
            } ${
              !searchQuery && selectedRegion === "featured"
                ? 'border-l-2 border-l-primary bg-menu-item-active shadow-sm'
                : 'hover:bg-menu-item-hover hover:shadow-sm hover:translate-x-[2px]'
            }`}
          >
            <span>
              <span className="text-primary font-medium">ION</span>{" "}
              <span className="text-foreground">FEATURED CHANNELS</span>
            </span>
            <ChevronRight className="w-4 h-4 text-muted-foreground" />
          </button>
          {menuData.regions.map((region) => (
            <button
              key={region.id}
              onClick={() => handleRegionClick(region.id)}
              className={`flex items-center justify-between px-4 ${menuItemPadding} text-left border-b border-menu-border transition-all duration-200 ${
                useBebasFont ? bebasStyles : 'text-sm uppercase tracking-wide'
              } ${
                !searchQuery && selectedRegion === region.id
                  ? 'border-l-2 border-l-primary bg-menu-item-active shadow-sm'
                  : 'hover:bg-menu-item-hover hover:shadow-sm hover:translate-x-[2px]'
              }`}
            >
              <span>
                <span className="text-primary font-medium">ION</span>{" "}
                <span className="text-foreground">{region.name}</span>
              </span>
              <ChevronRight className="w-4 h-4 text-muted-foreground" />
            </button>
          ))}
        </div>

        {/* Content Area */}
        <div className={`flex-1 p-[0.5rem] overflow-y-auto ${
          isMobile && mobileView === "regions" ? 'hidden' : ''
        }`}>
          {/* Search Results */}
          {filteredItems && filteredItems.length > 0 && (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">
              {filteredItems.map((item, index) => {
                // For countries, make them clickable to navigate to their states
                if (item.type === "country") {
                  return (
                    <button
                      key={`${item.type}-${item.id || item.name}-${index}`}
                      onClick={() => {
                        setSelectedRegion(item.regionId);
                        handleCountryClick(item.id);
                        setSearchQuery("");
                      }}
                      className={`group flex items-center gap-2 px-3 py-2.5 text-left text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] w-full ${
                        useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                      }`}
                    >
                      {item.id && (
                        <img 
                          src={`https://iblog.bz/assets/flags/${countryCodeMap[item.id] || item.id}.svg`}
                          alt={item.name}
                          className="w-5 h-4 object-cover flex-shrink-0"
                        />
                      )}
                      <span className="truncate flex-1">
                        <span className="text-primary font-medium">ION</span>{" "}
                        {item.name}
                      </span>
                      {item.id && (
                        <span className="text-muted-foreground/60 text-xs ml-auto flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                          ({(countryCodeMap[item.id] || item.id).toUpperCase()})
                        </span>
                      )}
                      <ChevronRight className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </button>
                  );
                }
                
                // For other types (states, cities, featured), keep them as links
                return (
                  <a
                    key={`${item.type}-${item.id || item.name}-${index}`}
                    href={item.url || "#"}
                    className={`group flex items-center gap-2 px-3 py-2.5 text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] ${
                      useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                    }`}
                  >
                    <MapPin className="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" />
                    <span className="truncate flex-1">
                      <span className="text-primary font-medium">ION</span>{" "}
                      {item.name}
                    </span>
                    {item.url && item.url !== "#" && (
                      <ExternalLink 
                        className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" 
                        onClick={(e) => {
                          e.preventDefault();
                          window.open(item.url, '_blank');
                        }}
                      />
                    )}
                  </a>
                );
              })}
            </div>
          )}

          {/* Featured Channels */}
          {!searchQuery && selectedRegion === "featured" && (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">
              {menuData.featuredChannels.map((channel) => (
                  <a
                    key={channel.name}
                    href={channel.url}
                    className={`group flex items-center gap-2 px-3 py-2.5 text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] ${
                      useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                    }`}
                  >
                  <MapPin className="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" />
                  <span className="truncate flex-1">
                    <span className="text-primary font-medium">ION</span>{" "}
                    {channel.name}
                  </span>
                  <ExternalLink
                    className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" 
                    onClick={(e) => {
                      e.preventDefault();
                      window.open(channel.url, '_blank');
                    }}
                  />
                </a>
              ))}
            </div>
          )}

          {/* Region View - Show Countries/States */}
          {!searchQuery && selectedRegion && selectedRegion !== "featured" && !selectedCountry && (
            <div className="flex gap-4">
              {currentRegion?.countries && currentRegion.countries.length > 0 && (
                <div className="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">
                  {currentRegion.countries.map((country: MenuItem) => {
                    const hasSubItems = country.states?.length > 0 || country.cities?.length > 0;
                    const countryUrl = generateUrl(country.name);
                    
                    if (hasSubItems) {
                      return (
                        <button
                          key={country.id}
                          onClick={() => handleCountryClick(country.id)}
                          className={`flex items-center gap-2 px-3 py-2.5 text-left text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 justify-between group hover:shadow-sm hover:scale-[1.01] ${
                            useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                          }`}
                        >
                          <div className="flex items-center gap-2 flex-1 min-w-0">
                            <img 
                              src={`https://iblog.bz/assets/flags/${countryCodeMap[country.id] || country.id}.svg`}
                              alt={country.name}
                              className="w-5 h-4 object-cover flex-shrink-0"
                            />
                            <span className="truncate flex-1">
                              <span className="text-primary font-medium">ION</span>{" "}
                              {country.name}
                            </span>
                            <span className="text-muted-foreground/60 text-xs ml-auto flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                              ({(countryCodeMap[country.id] || country.id).toUpperCase()})
                            </span>
                          </div>
                          <ChevronRight className="w-4 h-4 opacity-50 group-hover:opacity-100 flex-shrink-0" />
                        </button>
                      );
                    } else {
                      return (
                        <a
                          key={country.id}
                          href={countryUrl}
                          className={`group flex items-center gap-2 px-3 py-2.5 text-left text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 justify-between hover:shadow-sm hover:scale-[1.01] ${
                            useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                          }`}
                        >
                          <div className="flex items-center gap-2 flex-1 min-w-0">
                            <img 
                              src={`https://iblog.bz/assets/flags/${countryCodeMap[country.id] || country.id}.svg`}
                              alt={country.name}
                              className="w-5 h-4 object-cover flex-shrink-0"
                            />
                            <span className="truncate flex-1">
                              <span className="text-primary font-medium">ION</span>{" "}
                              {country.name}
                            </span>
                            <span className="text-muted-foreground/60 text-xs ml-auto flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                              ({(countryCodeMap[country.id] || country.id).toUpperCase()})
                            </span>
                          </div>
                          <ExternalLink 
                            className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" 
                            onClick={(e) => {
                              e.preventDefault();
                              window.open(countryUrl, '_blank');
                            }}
                          />
                        </a>
                      );
                    }
                  })}
                </div>
              )}
            </div>
          )}

          {/* Country View - Show States/Cities */}
          {!searchQuery && currentCountry && (
            <>
              {'states' in currentCountry && currentCountry.states && currentCountry.states.length > 0 && (
                renderStatesGrid(currentCountry.states)
              )}
              {'cities' in currentCountry && currentCountry.cities && currentCountry.cities.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">
                  {currentCountry.cities.map((city: any) => {
                    const cityUrl = city.url || generateUrl(city.name);
                    return (
                      <a
                        key={city.name}
                        href={cityUrl}
                        className={`group flex items-center gap-2 px-3 py-2.5 text-muted-foreground hover:bg-menu-item-hover hover:text-primary rounded transition-all duration-200 relative hover:shadow-sm hover:scale-[1.01] ${
                          useBebasFont ? bebasStyles : 'text-xs uppercase tracking-wide'
                        }`}
                      >
                        <MapPin className="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" />
                        <span className="truncate flex-1">
                          <span className="text-primary font-medium">ION</span>{" "}
                          {city.name}
                        </span>
                        <ExternalLink
                          className="w-3.5 h-3.5 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" 
                          onClick={(e) => {
                            e.preventDefault();
                            window.open(cityUrl, '_blank');
                          }}
                        />
                      </a>
                    );
                  })}
                </div>
              )}
            </>
          )}
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
