import { Flag } from '@/components/flag';
import { cn } from '@/lib/utils';
import type { StandingRow } from '@/types/pools';

const FORM_STYLES: Record<string, string> = {
    W: 'bg-primary',
    D: 'bg-zinc-400',
    L: 'bg-destructive',
};

/** Compact W/D/L form chips, oldest first, most recent on the right. */
function FormGuide({ form }: { form: string[] }) {
    if (form.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="inline-flex items-center gap-[3px]">
            {form.map((result, index) => (
                <span
                    key={index}
                    title={result}
                    className={cn(
                        'inline-flex size-[18px] items-center justify-center rounded-[5px] font-display text-[10px] font-semibold text-white',
                        FORM_STYLES[result] ?? 'bg-zinc-400',
                    )}
                >
                    {result}
                </span>
            ))}
        </span>
    );
}

/**
 * A group standings table in the Brothers Bets identity: bold uppercase headers, ink cells with a
 * `font-display` rank, a green tint on the qualifying top two, an amber tint on the
 * best-third (3rd) row, W/D/L form chips, and a qualify/best-third key beneath. Shared by
 * the prediction wizard (projected from a user's picks) and the tournament group page
 * (official live results).
 */
export function StandingsTable({ standings }: { standings: StandingRow[] }) {
    return (
        <div className="text-foreground">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse tabular-nums">
                    <thead>
                        <tr className="[&>th]:px-1 [&>th]:py-2 [&>th]:text-center [&>th]:text-[10px] [&>th]:font-bold [&>th]:tracking-[0.03em] [&>th]:text-muted-foreground [&>th]:uppercase">
                            <th className="!text-left">#</th>
                            <th className="!pl-1.5 !text-left">Team</th>
                            <th title="Played">P</th>
                            <th title="Won">W</th>
                            <th title="Drawn">D</th>
                            <th title="Lost">L</th>
                            <th title="Goals for">GF</th>
                            <th title="Goals against">GA</th>
                            <th title="Goal difference">GD</th>
                            <th title="Points">Pts</th>
                            <th
                                className="!pl-1.5 !text-left"
                                title="Form (most recent last)"
                            >
                                Form
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {standings.map((row) => {
                            const qualifies = row.rank <= 2;
                            const bestThird = row.rank === 3;

                            return (
                                <tr
                                    key={row.team?.id ?? row.rank}
                                    className={cn(
                                        '[&>td]:border-t [&>td]:border-border [&>td]:px-1 [&>td]:py-2 [&>td]:text-center [&>td]:text-[12.5px] [&>td]:font-bold [&>td]:whitespace-nowrap',
                                        qualifies && '[&>td]:bg-primary/[0.07]',
                                        bestThird && '[&>td]:bg-amber/[0.09]',
                                    )}
                                >
                                    <td
                                        className={cn(
                                            '!text-left font-display',
                                            qualifies
                                                ? 'text-pitch-deep dark:text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {row.rank}
                                    </td>
                                    <td className="!pl-1.5 !text-left">
                                        <span className="inline-flex items-center gap-2">
                                            <Flag
                                                team={row.team}
                                                className="h-3.5 w-5"
                                            />
                                            <span className="truncate">
                                                {row.team?.name ?? '—'}
                                            </span>
                                            {row.tied && (
                                                <span
                                                    aria-label="Tied"
                                                    title="Level on every tiebreaker — this projected order is just a guess"
                                                    className="inline-flex size-[15px] shrink-0 items-center justify-center rounded-[4px] bg-muted text-[10px] font-bold text-muted-foreground"
                                                >
                                                    =
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                    <td>{row.played}</td>
                                    <td>{row.won}</td>
                                    <td>{row.drawn}</td>
                                    <td>{row.lost}</td>
                                    <td>{row.goals_for}</td>
                                    <td>{row.goals_against}</td>
                                    <td className="text-muted-foreground">
                                        {row.goal_difference > 0
                                            ? `+${row.goal_difference}`
                                            : row.goal_difference}
                                    </td>
                                    <td className="font-display">
                                        {row.points}
                                    </td>
                                    <td className="!pl-1.5 !text-left">
                                        <FormGuide form={row.form} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="mt-1 flex flex-wrap gap-4 border-t border-border pt-2 text-[10.5px] font-semibold text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className="size-2.5 rounded-[3px] bg-primary"
                    />
                    Qualify (top 2)
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className="size-2.5 rounded-[3px] bg-amber"
                    />
                    Best third-placed spot
                </span>
            </div>
        </div>
    );
}
