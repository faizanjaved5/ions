import { createRoot, Root } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import Header from "@/components/Header";
// Note: Do NOT import index.css here. We generate a standalone CSS file via Tailwind CLI
// and load it from PHP to avoid duplicating styles and to keep JS bundle lean.

type MountTarget = string | HTMLElement | undefined;

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

export function mount(target?: MountTarget): HTMLElement {
  const el = resolveTarget(target);
  if (mounted.has(el)) return el;
  const root = createRoot(el);
  root.render(
    <ThemeProvider
      attribute="class"
      defaultTheme="dark"
      enableSystem={false}
      storageKey="ion-theme"
    >
      <Header />
    </ThemeProvider>
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
