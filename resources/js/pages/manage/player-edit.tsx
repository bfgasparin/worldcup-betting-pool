import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import manage from '@/routes/manage';
import type { BreadcrumbItem } from '@/types/navigation';

/** The "device language" sentinel — the server normalises it to null (follow the device). */
const DEVICE = 'device';

interface PoolChip {
    id: number;
    name: string;
    tournament_name: string;
}

interface PlayerEdit {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    locale: string | null;
    locked: boolean;
    pools: PoolChip[];
}

interface PlayerEditProps {
    player: PlayerEdit;
    pools: PoolChip[];
    supportedLocales: Record<string, string>;
}

/** The banner shown above a locked account, reminding the admin it's no longer theirs to edit. */
function LockedBanner() {
    const { t } = useTranslation();

    return (
        <Alert>
            <Lock />
            <AlertTitle>{t('This account belongs to the player')}</AlertTitle>
            <AlertDescription>
                {t(
                    'A login email has been set, so the player now manages their own account. Admin editing is disabled — only they can change their details, from their profile settings.',
                )}
            </AlertDescription>
        </Alert>
    );
}

/** A read-only chip list of the pools a player has already joined. */
function JoinedPools({ pools }: { pools: PoolChip[] }) {
    const { t } = useTranslation();

    if (pools.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('Not in any pool yet.')}
            </p>
        );
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {pools.map((pool) => (
                <span
                    key={pool.id}
                    className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"
                >
                    {pool.name}
                </span>
            ))}
        </div>
    );
}

/** The set-login-email panel: a one-way door, gated behind a confirm dialog. */
function SetEmailPanel({ player }: { player: PlayerEdit }) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const form = useForm<{ email: string }>({ email: '' });

    const setEmail = () => {
        form.patch(manage.players.email(player.id).url, {
            preserveScroll: true,
            // Keep the dialog open on error so the validation message is shown in context.
            onSuccess: () => setConfirmOpen(false),
        });
    };

    return (
        <section className="card-elevated flex flex-col gap-4 rounded-3xl border border-border p-6">
            <div className="flex flex-col gap-1">
                <h2 className="text-lg font-semibold text-foreground">
                    {t('Set login email')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Once set, the player can sign in — and the account is permanently handed to them. This cannot be undone.',
                    )}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">{t('Email address')}</Label>
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
                <InputError message={form.errors.email} />
            </div>

            <Dialog
                open={confirmOpen}
                onOpenChange={(next) => {
                    setConfirmOpen(next);

                    if (!next) {
                        form.clearErrors();
                    }
                }}
            >
                <DialogTrigger asChild>
                    <Button
                        className="w-fit"
                        disabled={form.data.email.trim() === ''}
                    >
                        {t('Set login email')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Set login email?')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Setting a login email permanently hands this account to the player. After this you can no longer edit their name, phone, language, or pools — only they can. This cannot be undone.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <InputError message={form.errors.email} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button onClick={setEmail} disabled={form.processing}>
                            {form.processing ? t('Saving…') : t('Set email')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

export default function PlayerEditPage({
    player,
    pools,
    supportedLocales,
}: PlayerEditProps) {
    const { t } = useTranslation();
    const form = useForm<{
        name: string;
        phone: string;
        locale: string;
        pools: number[];
    }>({
        name: player.name,
        phone: player.phone ?? '',
        locale: player.locale ?? DEVICE,
        pools: [],
    });

    const joinedIds = new Set(player.pools.map((pool) => pool.id));
    const addable = pools.filter((pool) => !joinedIds.has(pool.id));

    const togglePool = (id: number) => {
        form.setData(
            'pools',
            form.data.pools.includes(id)
                ? form.data.pools.filter((poolId) => poolId !== id)
                : [...form.data.pools, id],
        );
    };

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(manage.players.update(player.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={player.name} />
            <div className="relative min-h-full bg-background">
                <div className="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <header className="mb-6 flex flex-col gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {player.name}
                        </h1>
                        <span className="text-sm text-muted-foreground">
                            {player.email ?? t('No email yet')}
                        </span>
                    </header>

                    {player.locked && (
                        <div className="mb-6">
                            <LockedBanner />
                        </div>
                    )}

                    <div className="flex flex-col gap-6">
                        <form
                            onSubmit={save}
                            className="card-elevated flex flex-col gap-5 rounded-3xl border border-border p-6"
                        >
                            <h2 className="text-lg font-semibold text-foreground">
                                {t('Details')}
                            </h2>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    disabled={player.locked}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">{t('Phone')}</Label>
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    disabled={player.locked}
                                    required
                                    inputMode="tel"
                                    autoComplete="off"
                                />
                                <InputError message={form.errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">{t('Language')}</Label>
                                <Select
                                    value={form.data.locale}
                                    onValueChange={(value) =>
                                        form.setData('locale', value)
                                    }
                                    disabled={player.locked}
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
                                                <SelectItem
                                                    key={code}
                                                    value={code}
                                                >
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
                                <JoinedPools pools={player.pools} />
                                {!player.locked && addable.length > 0 && (
                                    <div className="mt-2 flex flex-col gap-2">
                                        <span className="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase">
                                            {t('Add to pools')}
                                        </span>
                                        {addable.map((pool) => (
                                            <label
                                                key={pool.id}
                                                className="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary/5"
                                            >
                                                <Checkbox
                                                    checked={form.data.pools.includes(
                                                        pool.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        togglePool(pool.id)
                                                    }
                                                />
                                                <span className="font-medium text-foreground">
                                                    {pool.name}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {t(pool.tournament_name)}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                                <InputError message={form.errors.pools} />
                            </div>

                            {!player.locked && (
                                <Button
                                    type="submit"
                                    className="w-fit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? t('Saving…') : t('Save')}
                                </Button>
                            )}
                        </form>

                        {!player.locked && <SetEmailPanel player={player} />}
                    </div>
                </div>
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage', href: manage.index() },
    { title: 'Players', href: manage.players.index() },
    { title: 'Edit player', href: manage.players.index() },
];

PlayerEditPage.layout = { breadcrumbs };
