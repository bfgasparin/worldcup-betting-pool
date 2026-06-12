import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Lock,
    Search,
    UserPlus,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { Translator } from '@/hooks/use-translation';
import manage from '@/routes/manage';
import type { BreadcrumbItem } from '@/types/navigation';
import type { Paginated } from '@/types/pools';

/** The "device language" sentinel — the server normalises it to null (follow the device). */
const DEVICE = 'device';

interface PoolChip {
    id: number;
    name: string;
    tournament_name: string;
}

interface JoinablePool {
    id: number;
    name: string;
    tournament_name: string;
}

interface PlayerRow {
    id: number;
    name: string;
    email: string | null;
    locale: string | null;
    locked: boolean;
    pools: PoolChip[];
}

interface PlayersIndexProps {
    players: Paginated<PlayerRow>;
    pools: JoinablePool[];
    supportedLocales: Record<string, string>;
    filters: { q: string };
}

/** Cluster joinable pools by the tournament they're played over, preserving server order. */
function groupPools(
    pools: JoinablePool[],
): { tournament: string; pools: JoinablePool[] }[] {
    const groups: { tournament: string; pools: JoinablePool[] }[] = [];
    const byName = new Map<
        string,
        { tournament: string; pools: JoinablePool[] }
    >();

    for (const pool of pools) {
        let group = byName.get(pool.tournament_name);

        if (!group) {
            group = { tournament: pool.tournament_name, pools: [] };
            byName.set(pool.tournament_name, group);
            groups.push(group);
        }

        group.pools.push(pool);
    }

    return groups;
}

/** Pending = no login email yet (still editable by an admin); Active = email set (locked). */
function StatusBadge({ locked }: { locked: boolean }) {
    const { t } = useTranslation();

    if (locked) {
        return (
            <Badge variant="default">
                <Lock />
                {t('Active')}
            </Badge>
        );
    }

    return <Badge variant="secondary">{t('Pending')}</Badge>;
}

/** The checklist of pools to pre-join, grouped by tournament. */
function PoolPicker({
    pools,
    selected,
    onToggle,
    t,
}: {
    pools: JoinablePool[];
    selected: number[];
    onToggle: (id: number) => void;
    t: Translator['t'];
}) {
    if (pools.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('No pools are currently accepting predictions.')}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {groupPools(pools).map((group) => (
                <div key={group.tournament} className="flex flex-col gap-2">
                    <span className="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase">
                        {t(group.tournament)}
                    </span>
                    {group.pools.map((pool) => (
                        <label
                            key={pool.id}
                            className="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary/5"
                        >
                            <Checkbox
                                checked={selected.includes(pool.id)}
                                onCheckedChange={() => onToggle(pool.id)}
                            />
                            <span className="font-medium text-foreground">
                                {pool.name}
                            </span>
                        </label>
                    ))}
                </div>
            ))}
        </div>
    );
}

/** The "Pre-register player" dialog: name (+ language) and optional pools, no email. */
function PreRegisterDialog({
    pools,
    supportedLocales,
}: {
    pools: JoinablePool[];
    supportedLocales: Record<string, string>;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        email: string;
        locale: string;
        pools: number[];
    }>({ name: '', email: '', locale: DEVICE, pools: [] });

    const togglePool = (id: number) => {
        form.setData(
            'pools',
            form.data.pools.includes(id)
                ? form.data.pools.filter((poolId) => poolId !== id)
                : [...form.data.pools, id],
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(manage.players.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <UserPlus className="size-4" />
                    {t('Pre-register player')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Pre-register player')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Create a player from a name. Add an email now to let them sign in straight away, or leave it blank and set it later.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoComplete="off"
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">
                            {t('Email address (optional)')}
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="off"
                            placeholder="player@example.com"
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Setting an email lets the player sign in now and locks the account to admin edits. Leave blank to set it later.',
                            )}
                        </p>
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="locale">{t('Language')}</Label>
                        <Select
                            value={form.data.locale}
                            onValueChange={(value) =>
                                form.setData('locale', value)
                            }
                        >
                            <SelectTrigger id="locale">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={DEVICE}>
                                    {t('Device language')}
                                </SelectItem>
                                {Object.entries(supportedLocales).map(
                                    ([code, label]) => (
                                        <SelectItem key={code} value={code}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.locale} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Pools')}</Label>
                        <PoolPicker
                            pools={pools}
                            selected={form.data.pools}
                            onToggle={togglePool}
                            t={t}
                        />
                        <InputError message={form.errors.pools} />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t('Saving…') : t('Pre-register')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Previous / next controls, shown only when the roster spans more than one page. */
function Pagination({ players }: { players: Paginated<PlayerRow> }) {
    const { t } = useTranslation();

    if (players.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-8 flex items-center justify-between gap-4">
            {players.prev_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={players.prev_page_url} preserveScroll>
                        <ChevronLeft className="size-4" />
                        {t('Previous')}
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    <ChevronLeft className="size-4" />
                    {t('Previous')}
                </Button>
            )}

            <span className="text-sm font-medium text-muted-foreground">
                {t('Page :current of :last', {
                    current: players.current_page,
                    last: players.last_page,
                })}
            </span>

            {players.next_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={players.next_page_url} preserveScroll>
                        {t('Next')}
                        <ChevronRight className="size-4" />
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    {t('Next')}
                    <ChevronRight className="size-4" />
                </Button>
            )}
        </nav>
    );
}

/** A single roster row: name + status, contact, pool chips — tappable into the edit screen. */
function PlayerListRow({ player }: { player: PlayerRow }) {
    const { t } = useTranslation();

    return (
        <Link
            href={manage.players.edit(player.id).url}
            className="press-soft flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/50"
        >
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-display text-base font-semibold text-foreground">
                        {player.name}
                    </span>
                    <StatusBadge locked={player.locked} />
                </div>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                    {player.email ?? t('No email yet')}
                </span>
                {player.pools.length > 0 && (
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {player.pools.map((pool) => (
                            <span
                                key={pool.id}
                                className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"
                            >
                                {pool.name}
                            </span>
                        ))}
                    </div>
                )}
            </div>
            <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
        </Link>
    );
}

export default function PlayersIndex({
    players,
    pools,
    supportedLocales,
    filters,
}: PlayersIndexProps) {
    const { t } = useTranslation();
    const [query, setQuery] = useState(filters.q);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const handle = setTimeout(() => {
            router.get(manage.players.index().url, query ? { q: query } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(handle);
    }, [query]);

    return (
        <>
            <Head title={t('Players')} />
            <div className="relative min-h-full bg-background">
                <div className="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <Users className="size-4 text-primary" />
                                {t('Admin')}
                            </span>
                            <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                {t('Players')}
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                                {t(
                                    'Pre-register players before they have an email, join them to pools, and set their login email when they’re ready.',
                                )}
                            </p>
                        </div>
                    </header>

                    <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative sm:max-w-sm sm:flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder={t('Search players')}
                                className="pl-9"
                            />
                        </div>
                        <PreRegisterDialog
                            pools={pools}
                            supportedLocales={supportedLocales}
                        />
                    </div>

                    {players.data.length > 0 ? (
                        <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                            {players.data.map((player) => (
                                <PlayerListRow
                                    key={player.id}
                                    player={player}
                                />
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('No players yet.')}
                        </p>
                    )}

                    <Pagination players={players} />
                </div>
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage', href: manage.index() },
    { title: 'Players', href: manage.players.index() },
];

PlayersIndex.layout = { breadcrumbs };
