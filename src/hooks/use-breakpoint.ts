import * as React from "react";

// Breakpoints aligned with Tailwind defaults
const MD = 768;
const LG = 1024;
const XL = 1280;

export function useBreakpoint() {
  const [width, setWidth] = React.useState<number>(() =>
    typeof window !== "undefined" ? window.innerWidth : XL
  );

  React.useEffect(() => {
    const onResize = () => setWidth(window.innerWidth);
    window.addEventListener("resize", onResize);
    // initialize on mount
    onResize();
    return () => window.removeEventListener("resize", onResize);
  }, []);

  const isXl = width >= XL;
  const isLg = width >= LG && width < XL;
  const isMd = width >= MD && width < LG;

  return { isMd, isLg, isXl } as const;
}
