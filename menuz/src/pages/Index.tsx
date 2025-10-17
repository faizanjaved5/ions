import Header from "@/components/Header";

const Index = () => {
  return (
    <div className="min-h-screen bg-background">
      <Header />
      <main className="container mx-auto px-4 flex items-center justify-center min-h-[calc(100vh-64px)]">
        <div className="text-center">
          <h1 className="font-bebas text-5xl uppercase tracking-wider">
            <span className="text-primary">ION</span>{" "}
            <span className="text-foreground">The Network of Champions</span>
          </h1>
        </div>
      </main>
    </div>
  );
};

export default Index;
