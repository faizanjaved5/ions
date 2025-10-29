export { };

declare global {
  interface Window {
    refreshUserState: () => Promise<void>;
    IonNavbar?: {
      init: (options: any) => void;
    };
  }
}
