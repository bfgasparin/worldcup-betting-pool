import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { index as games } from '@/routes/games';

const STATS = [
    { stat: 'Live', label: 'Match predictions' },
    { stat: 'Season', label: 'Long leaderboards' },
    { stat: 'Bragging', label: 'Rights on the line' },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const authed = Boolean(auth.user);

    return (
        <>
            <Head title="Welcome" />
            <div className="hero relative flex min-h-svh flex-col overflow-hidden">
                <div className="hero-lines" />
                {/* Stadium-light glows */}
                <div className="pointer-events-none absolute inset-0 -z-10">
                    <div className="absolute -top-24 -right-28 size-[34rem] rounded-full bg-primary/25 blur-[120px]" />
                    <div className="absolute -bottom-40 -left-32 size-[26rem] rounded-full bg-accent/15 blur-[120px]" />
                </div>

                <nav className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-7">
                    <span className="inline-flex items-baseline font-display text-xl font-semibold tracking-tight text-foreground">
                        Brothers
                        <span className="ml-2 text-[10px] font-bold tracking-[0.22em] text-muted-foreground uppercase">
                            Betting Pool
                        </span>
                    </span>
                    <Button asChild size="sm" variant="outline">
                        <Link href={authed ? games() : login()}>
                            {authed ? 'Games' : 'Log in'}
                        </Link>
                    </Button>
                </nav>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col items-center justify-center px-6 py-16 text-center">
                    <span className="inline-flex items-center gap-2.5 rounded-full bg-muted px-4 py-1.5 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                        <span className="bg-brand-gradient size-2 rounded-full" />
                        Members Only · Invite Required
                    </span>

                    <h1 className="mt-6 max-w-3xl text-5xl font-semibold tracking-tight text-balance text-foreground sm:text-6xl lg:text-7xl">
                        Where the <span className="text-primary">pool</span>{' '}
                        plays for <span className="text-gold">glory</span>.
                    </h1>

                    <p className="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground">
                        Predict the fixtures, climb the leaderboard, and settle
                        the bragging rights. The private betting pool for the
                        Brothers crew — every match, every season.
                    </p>

                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row">
                        <Button asChild size="lg" className="group">
                            <Link href={authed ? games() : login()}>
                                {authed ? 'Go to Games' : 'Log in'}
                                <span className="transition-transform group-hover:translate-x-1">
                                    →
                                </span>
                            </Link>
                        </Button>
                        {!authed && (
                            <p className="text-sm text-muted-foreground">
                                Got an invite? Log in to join the action.
                            </p>
                        )}
                    </div>

                    <dl className="mt-20 grid w-full max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                        {STATS.map((item) => (
                            <div
                                key={item.label}
                                className="rounded-2xl border border-border bg-card p-6 shadow-[var(--sh-sm)]"
                            >
                                <dt className="font-display text-2xl font-semibold text-primary">
                                    {item.stat}
                                </dt>
                                <dd className="mt-1 text-sm text-muted-foreground">
                                    {item.label}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </main>

                <footer className="relative z-10 mx-auto w-full max-w-6xl px-6 py-8 text-center text-sm text-muted-foreground">
                    Brothers Betting Pool — a private members' pool.
                </footer>
            </div>
        </>
    );
}
