import { createContext, useContext } from "react";

type PortalContextValue = {
  container: HTMLElement | null;
};

const PortalContext = createContext<PortalContextValue>({ container: null });

export const PortalProvider = PortalContext.Provider;

export function usePortalContainer(): HTMLElement | null {
  return useContext(PortalContext).container;
}


