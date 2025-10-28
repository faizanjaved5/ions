import Header from "@/components/Header";

const Upload = () => {
  return (
    <div className="min-h-screen bg-background">
      <Header />
      <main className="container mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold mb-6">Upload</h1>
        <div className="bg-card rounded-lg border p-6">
          <p className="text-muted-foreground">Upload form will go here.</p>
        </div>
      </main>
    </div>
  );
};

export default Upload;
