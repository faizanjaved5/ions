import { createRoot } from "react-dom/client";
import { useEffect, useState } from "react";
import Header from "@/components/Header";
import { AuthProvider } from "@/contexts/AuthContext";
import { PortalProvider } from "@/components/ui/portal-provider";
import { ThemeProvider } from "next-themes";
import "../index.css";

type EmbeddedNotification = { id: number; message: string; time: string; read: boolean };
type EmbeddedMenuItem = { label: string; link: string; icon: string };
type EmbeddedUser = {
  name: string;
  email: string;
  avatar: string;
  notifications: EmbeddedNotification[];
  menuItems: EmbeddedMenuItem[];
};

type InitOptions = {
  target: string | HTMLElement;
  cssUrl: string;
  userDataUrl?: string;
  userData?: {
    isLoggedIn: boolean;
    user?: EmbeddedUser | null;
  };
  signInUrl?: string;
  signOutUrl?: string;
  uploadUrl?: string;
  onSearch?: (q: string) => void;
  theme?: "dark" | "light";
  spriteUrl?: string;
};

function resolveTarget(target: string | HTMLElement): HTMLElement | null {
  if (typeof target === "string") return document.querySelector(target);
  return target || null;
}

function ensureGlobalCss(cssUrl: string) {
  const id = "ion-navbar-global-css";
  if (document.getElementById(id)) return;
  const link = document.createElement("link");
  link.id = id;
  link.rel = "stylesheet";
  link.href = cssUrl;
  document.head.appendChild(link);
}

export function init(options: InitOptions) {
  const mountEl = resolveTarget(options.target);
  if (!mountEl) return;

  // Create a host and shadow root
  const host = document.createElement("div");
  mountEl.appendChild(host);
  const shadow = host.attachShadow({ mode: "open" });

  // Inject CSS into shadow root
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = options.cssUrl;
  shadow.appendChild(link);

  // Ensure CSS variables exist on the shadow host (some Tailwind vars target :root/body)
  const vars = document.createElement("style");
  vars.textContent = `
    :host {
      --background: 0 0% 100%;
      --foreground: 0 0% 10%;
      --card: 0 0% 100%;
      --card-foreground: 0 0% 10%;
      --popover: 0 0% 100%;
      --popover-foreground: 0 0% 10%;
      --primary: 29 36% 46%;
      --primary-foreground: 0 0% 100%;
      --secondary: 0 0% 96%;
      --secondary-foreground: 0 0% 10%;
      --muted: 0 0% 96%;
      --muted-foreground: 0 0% 40%;
      --accent: 29 36% 46%;
      --accent-foreground: 0 0% 100%;
      --destructive: 0 84.2% 60.2%;
      --destructive-foreground: 0 0% 100%;
      --border: 0 0% 90%;
      --input: 0 0% 96%;
      --ring: 29 36% 46%;
      --radius: 0.25rem;
      --menu-bg: 0 0% 98%;
      --menu-item-hover: 29 36% 95%;
      --menu-item-active: 29 40% 92%;
      --menu-border: 0 0% 90%;
      --scrollbar-track: 0 0% 98%;
      --scrollbar-thumb: 29 36% 46%;
    }
    :host(.dark), :host .dark {
      --background: 216 20% 15%;
      --foreground: 210 20% 85%;
      --card: 218 22% 18%;
      --card-foreground: 210 20% 85%;
      --popover: 218 22% 18%;
      --popover-foreground: 210 20% 85%;
      --primary: 29 36% 46%;
      --primary-foreground: 0 0% 100%;
      --secondary: 220 18% 25%;
      --secondary-foreground: 210 20% 85%;
      --muted: 220 18% 22%;
      --muted-foreground: 215 15% 60%;
      --accent: 29 36% 46%;
      --accent-foreground: 0 0% 100%;
      --destructive: 0 84.2% 60.2%;
      --destructive-foreground: 0 0% 100%;
      --border: 220 18% 25%;
      --input: 220 18% 22%;
      --ring: 29 36% 46%;
      --menu-bg: 218 22% 16%;
      --menu-item-hover: 29 25% 22%;
      --menu-item-active: 29 30% 26%;
      --menu-border: 220 18% 22%;
      --scrollbar-track: 218 22% 16%;
      --scrollbar-thumb: 29 36% 46%;
    }
  `;
  shadow.appendChild(vars);

  // Inject fonts used by the navbar
  const pre1 = document.createElement("link");
  pre1.rel = "preconnect";
  pre1.href = "https://fonts.googleapis.com";
  shadow.appendChild(pre1);

  const pre2 = document.createElement("link");
  pre2.rel = "preconnect";
  pre2.href = "https://fonts.gstatic.com";
  pre2.crossOrigin = "";
  shadow.appendChild(pre2);

  const fontLink = document.createElement("link");
  fontLink.rel = "stylesheet";
  fontLink.href = "https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap";
  shadow.appendChild(fontLink);

  // Minimal runtime fallback for critical responsive utilities in case of purge
  const fallback = document.createElement("style");
  fallback.textContent = `
    @media (min-width:768px){
      .md\\:hidden{display:none}
      .md\\:flex{display:flex}
    }
    @media (min-width:640px){
      .sm\\:relative{position:relative}
      .sm\\:absolute{position:absolute}
      .sm\\:right-1{right:0.25rem}
      .sm\\:top-1\\2f 2{top:50%}
      .sm\\:-translate-y-1\\2f 2{--tw-translate-y:-50%; transform:translate(var(--tw-translate-x,0), var(--tw-translate-y))}
      .sm\\:w-auto{width:auto}
    }
  `;
  shadow.appendChild(fallback);

  // Also ensure CSS is available globally for Radix portals rendered to document.body
  ensureGlobalCss(options.cssUrl);

  // Create container for React inside shadow root
  const container = document.createElement("div");
  // Apply initial theme class for Tailwind dark styles inside shadow
  if ((options.theme || "dark") === "dark") {
    container.classList.add("dark");
    host.classList.add("dark"); // ensure portal siblings inherit dark variables
  } else {
    host.classList.remove("dark");
  }
  shadow.appendChild(container);

  // Create a dedicated element for portals inside the shadow DOM
  const portalEl = document.createElement("div");
  shadow.appendChild(portalEl);

  const onSearch = (q: string) => {
    if (options.onSearch) return options.onSearch(q);
    window.location.href = `/search/?q=${q}`;
  };

  function EmbedApp() {
    const initialMode: "dark" | "light" = (options.theme === "light" ? "light" : "dark");
    const [mode, setMode] = useState<"dark" | "light">(initialMode);
    const disableThemeTransitions = () => {
      const css = "*{transition:none!important} .transition,*::before,*::after{transition:none!important}";
      const global = document.createElement("style");
      global.textContent = css;
      document.head.appendChild(global);
      const local = document.createElement("style");
      local.textContent = css;
      shadow.appendChild(local);
      // Force reflow
      void document.body.offsetHeight;
      return () => { global.remove(); local.remove(); };
    };
    useEffect(() => {
      if (mode === "dark") {
        container.classList.add("dark");
        host.classList.add("dark");
        document.body.setAttribute("data-theme", "dark");
      } else {
        container.classList.remove("dark");
        host.classList.remove("dark");
        document.body.setAttribute("data-theme", "light");
      }
      return () => {
        // No-op on unmount; leave the current theme value so the page stays consistent
      };
    }, [mode]);
    return (
      <ThemeProvider attribute="class" forcedTheme={mode} enableSystem={false} disableTransitionOnChange storageKey="ion-theme-embed">
        <AuthProvider userDataUrl={options.userDataUrl} initialUserData={options.userData}>
          <PortalProvider value={{ container: portalEl }}>
            <Header
              onSearch={onSearch}
              uploadUrl={options.uploadUrl}
              signInUrl={options.signInUrl}
              signOutUrl={options.signOutUrl}
              linkType="anchor"
              disableThemeToggle={false}
              externalTheme={mode}
              onExternalThemeToggle={() => {
                const restore = disableThemeTransitions();
                setMode((prev) => (prev === "dark" ? "light" : "dark"));
                // Allow next frame to apply then restore transitions
                setTimeout(restore, 60);
              }}
              spriteUrl={options.spriteUrl}
            />
          </PortalProvider>
        </AuthProvider>
      </ThemeProvider>
    );
  }

  createRoot(container).render(<EmbedApp />);
}

// For IIFE global exposure when not using lib mode exports
if (typeof window !== "undefined") {
  window.IonNavbar = window.IonNavbar || { init };
  window.IonNavbar.init = init;
}

export default { init };


