import { useSearchParams } from "react-router-dom";
import { useEffect, useState } from "react";
import Header from "@/components/Header";

const Search = () => {
  const [searchParams] = useSearchParams();
  const query = searchParams.get("q") || "";
  const [decodedQuery, setDecodedQuery] = useState("");

  useEffect(() => {
    if (query) {
      setDecodedQuery(decodeURIComponent(query));
    }
  }, [query]);

  return (
    <div className="min-h-screen bg-background">
      <Header />
      <main className="container mx-auto px-4 py-8">
        <div className="max-w-4xl mx-auto">
          <h1 className="font-bebas text-4xl md:text-5xl uppercase tracking-wider mb-2">
            Search Results
          </h1>
          {decodedQuery && (
            <p className="text-lg text-muted-foreground mb-8">
              Showing results for: <span className="text-foreground font-semibold">"{decodedQuery}"</span>
            </p>
          )}
          
          <div className="space-y-6">
            <div className="text-center py-12">
              <p className="text-muted-foreground">
                Search functionality coming soon. Your query has been captured.
              </p>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
};

export default Search;
