import { createRoot, Root } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import Header from "@/components/Header";
import "@/index.css";
import { PortalProvider } from "@/components/ui/portal-provider";
import { AuthProvider } from "@/contexts/AuthContext";

type MountTarget = string | HTMLElement | undefined;
type MountOptions = {
  useShadowDom?: boolean;
  cssHref?: string;
};

const mounted = new WeakMap<HTMLElement, Root>();

function resolveTarget(target?: MountTarget): HTMLElement {
  if (typeof target === "string") {
    const el = document.querySelector<HTMLElement>(target);
    if (el) return el;
  } else if (target instanceof HTMLElement) {
    return target;
  }
  let existing = document.getElementById("ion-navbar-root");
  if (existing) return existing;
  const container = document.createElement("div");
  container.id = "ion-navbar-root";
  document.body.prepend(container);
  return container;
}

export function mount(
  target?: MountTarget,
  options?: MountOptions
): HTMLElement {
  const el = resolveTarget(target);
  if (mounted.has(el)) return el;

  if (options?.useShadowDom) {
    const host = el;
    const shadow = host.shadowRoot ?? host.attachShadow({ mode: "open" });
    const shadowContainer = document.createElement("div");
    shadow.appendChild(shadowContainer);

    const startRender = () => {
      const root = createRoot(shadowContainer);
      root.render(
        <AuthProvider>
          <ThemeProvider
            attribute="class"
            defaultTheme="dark"
            enableSystem={false}
            storageKey="ion-theme"
          >
            <PortalProvider container={shadow}>
              <Header />
            </PortalProvider>
          </ThemeProvider>
        </AuthProvider>
      );
      mounted.set(host, root);
    };

    if (options.cssHref) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = options.cssHref;
      shadow.appendChild(link);
      let done = false;
      const resolve = () => {
        if (done) return;
        done = true;
        startRender();
      };
      link.addEventListener("load", resolve, { once: true });
      link.addEventListener("error", resolve, { once: true });
      setTimeout(resolve, 200);
    } else {
      startRender();
    }
    return host;
  }

  const root = createRoot(el);
  root.render(
    <AuthProvider>
      <ThemeProvider
        attribute="class"
        defaultTheme="dark"
        enableSystem={false}
        storageKey="ion-theme"
      >
        <Header />
      </ThemeProvider>
    </AuthProvider>
  );
  mounted.set(el, root);
  return el;
}

export function unmount(target?: MountTarget): void {
  const el = resolveTarget(target);
  const root = mounted.get(el);
  if (root) {
    root.unmount();
    mounted.delete(el);
  }
}

// Expose globals for IIFE usage
// @ts-expect-error attach to window for non-module consumers
window.IONNavbar = { mount, unmount };
