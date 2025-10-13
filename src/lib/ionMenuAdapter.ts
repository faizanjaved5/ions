type IonItem = {
  title: string;
  url?: string | null;
  children?: IonItem[];
};

type IonMenu = { menu?: { name?: string }; items?: IonItem[] };
async function loadMenus(): Promise<IonMenu[]> {
  const mod = await import("@/data/ion-menu.json");
  return (mod as unknown as { default: IonMenu[] }).default;
}

export async function getMenuItemsByNameAsync(name: string): Promise<IonItem[]> {
  const menus = await loadMenus();
  const menu = menus.find((m) => m.menu?.name === name);
  return menu?.items ?? [];
}

export const getConnectionsAsync = () => getMenuItemsByNameAsync("Connect.ions (New)");
export const getNetworksAsync = () => getMenuItemsByNameAsync("ION Networks");
export const getInitiativesAsync = () => getMenuItemsByNameAsync("Ionitiatives");
export const getIonLocalAsync = () => getMenuItemsByNameAsync("ION Local");

export type { IonItem };


