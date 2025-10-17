import React, { createContext, useContext } from "react";

type PortalContainer = HTMLElement | ShadowRoot | null | undefined;

const PortalContainerContext = createContext<PortalContainer>(undefined);

export function usePortalContainer(): PortalContainer {
  return useContext(PortalContainerContext);
}

type PortalProviderProps = {
  container?: PortalContainer;
  children: React.ReactNode;
};

export function PortalProvider({ container, children }: PortalProviderProps) {
  return (
    <PortalContainerContext.Provider value={container}>
      {children}
    </PortalContainerContext.Provider>
  );
}
