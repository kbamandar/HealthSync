interface SharePageProps {
  params: Promise<{ token: string }>;
}

export default async function SharePage({ params }: SharePageProps) {
  const { token } = await params;

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-2 p-8 text-center">
      <h1 className="text-2xl font-semibold">Shared health record</h1>
      <p className="text-sm text-neutral-500">
        Public share view for token <code>{token}</code> — wired to{" "}
        <code>GET /public/share/:token</code> in Sprint 6.
      </p>
    </main>
  );
}
