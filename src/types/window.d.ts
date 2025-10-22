export {};

declare global {
  interface Window {
    refreshUserState: () => Promise<void>;
    __ION_PROFILE_HREF?: string;
    __ION_PROFILE_DATA?: unknown;
  }
}
