export {};

declare global {
  interface Window {
    refreshUserState: () => Promise<void>;
  }
}
