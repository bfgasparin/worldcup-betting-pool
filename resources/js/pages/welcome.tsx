import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { login } from '@/routes';
import { index as games } from '@/routes/games';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="relative flex min-h-screen flex-col overflow-hidden bg-background text-foreground">
                {/* Backdrop glow */}
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -top-40 -left-40 size-[28rem] rounded-full bg-primary/25 blur-3xl" />
                    <div className="absolute -right-40 -bottom-40 size-[28rem] rounded-full bg-accent/20 blur-3xl" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,transparent,var(--background))]" />
                </div>

                <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-2.5">
                        <div className="flex aspect-square size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-6 fill-current" />
                        </div>
                        <span className="text-base font-bold tracking-tight">
                            FF&amp;A Betting Pool
                        </span>
                    </div>
                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={games()}
                                className="rounded-md border border-border px-5 py-2 text-sm font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                Tournaments
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="rounded-md border border-border px-5 py-2 text-sm font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                Log in
                            </Link>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col items-center justify-center px-6 py-16 text-center">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                        <span className="size-1.5 rounded-full bg-accent" />
                        Members Only · Invite Required
                    </span>

                    <h1 className="max-w-3xl text-5xl font-black tracking-tight text-balance sm:text-6xl lg:text-7xl">
                        Where the
                        <span className="text-primary"> pool </span>
                        plays for
                        <span className="text-accent"> glory</span>.
                    </h1>

                    <p className="mt-6 max-w-xl text-lg text-pretty text-muted-foreground">
                        Predict the fixtures, climb the leaderboard, and settle
                        the bragging rights. The private betting pool for the
                        FF&amp;A crew — every match, every season.
                    </p>

                    <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row">
                        <Link
                            href={auth.user ? games() : login()}
                            className="group inline-flex items-center gap-2 rounded-md bg-primary px-8 py-3.5 text-base font-bold text-primary-foreground shadow-lg shadow-primary/25 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40"
                        >
                            {auth.user ? 'Go to Tournaments' : 'Log in'}
                            <span className="transition-transform group-hover:translate-x-1">
                                →
                            </span>
                        </Link>
                        <p className="text-sm text-muted-foreground">
                            Got an invite? Log in to join the action.
                        </p>
                    </div>

                    <dl className="mt-20 grid w-full max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                        {[
                            { stat: 'Live', label: 'Match predictions' },
                            { stat: 'Season', label: 'Long leaderboards' },
                            { stat: 'Bragging', label: 'Rights on the line' },
                        ].map((item) => (
                            <div
                                key={item.label}
                                className="rounded-xl border border-border bg-card/60 p-6 backdrop-blur-sm"
                            >
                                <dt className="text-2xl font-black text-primary">
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
                    FF&amp;A Betting Pool — a private members' pool.
                </footer>
            </div>
        </>
    );
}
