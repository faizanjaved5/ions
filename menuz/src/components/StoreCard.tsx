import { ShoppingBag } from "lucide-react";

interface Store {
  s: string; // store name
  url: string; // partial URL
  img: string; // partial image path
  tab: string; // tab category
}

interface StoreCardProps {
  store: Store;
}

const StoreCard = ({ store }: StoreCardProps) => {
  const fullUrl = `https://mallofchampions.com/collections${store.url}`;
  
  const fullImageUrl = store.img.startsWith('http') 
    ? store.img 
    : `https://mallofchampions.com/cdn/shop/products${store.img}`;

  return (
    <a
      href={fullUrl}
      target="_blank"
      rel="noopener noreferrer"
      className="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105"
    >
      <div className="mb-3 flex h-20 w-20 items-center justify-center rounded-lg bg-white/90 dark:bg-white/95 p-2 overflow-hidden border border-border/20 group-hover:shadow-md transition-all duration-200">
        <img
          src={fullImageUrl}
          alt={`${store.s} logo`}
          className="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-200"
          onError={(e) => {
            const target = e.target as HTMLImageElement;
            target.style.display = "none";
            const fallback = target.nextElementSibling as HTMLElement;
            if (fallback) fallback.style.display = "flex";
          }}
        />
        <div className="hidden h-full w-full items-center justify-center bg-background/50 rounded">
          <ShoppingBag className="h-8 w-8 text-primary group-hover:scale-110 transition-transform" />
        </div>
      </div>
      <span className="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
        {store.s}
      </span>
    </a>
  );
};

export default StoreCard;
