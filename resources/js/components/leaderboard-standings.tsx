import { Loader2, LocateFixed } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { MovementArrow } from '@/components/movement-arrow';
import { Button } from '@/components/ui/button';
import { prizeForPlace } from '@/lib/prizes';
import type { BoardRow, LeaderboardBoard, PoolPricing } from '@/types/pools';

const INITIAL_ROWS = 25;
const ROW_STEP = 25;

/** The board's secondary stat, formatted with its label (e.g. "27 team goals"). */
function secondaryStat(
    board: LeaderboardBoard,
    value: number | null,
): string | null {
    if (board.secondary_stat_label === null || value === null) {
        return null;
    }

    return `${value} ${board.secondary_stat_label.toLowerCase()}`;
}

function formatValue(value: number | null): string {
    return value === null ? '—' : value.toLocaleString();
}

/**
 * The full standings table for a board, sized for hundreds of players: it renders a slice of the
 * rows and reveals more as the viewer scrolls (an IntersectionObserver sentinel), so the DOM never
 * holds the whole list at once. A sticky bar keeps the viewer's own position in view whenever their
 * row is off-screen, with a "Jump to me" action that scrolls straight to it.
 *
 * Remount this (via a `key` on the active board + matchday) so the revealed count resets when the
 * viewer switches board or travels to another matchday.
 */
export function LeaderboardStandings({
    rows,
    board,
    showPrizes,
    pricing,
}: {
    rows: BoardRow[];
    board: LeaderboardBoard;
    showPrizes: boolean;
    pricing: PoolPricing;
}) {
    const [visibleCount, setVisibleCount] = useState(INITIAL_ROWS);
    const [meVisible, setMeVisible] = useState(false);

    const meElementRef = useRef<HTMLDivElement | null>(null);
    const meObserverRef = useRef<IntersectionObserver | null>(null);

    const myIndex = rows.findIndex((row) => row.is_me);
    const myRow = myIndex >= 0 ? rows[myIndex] : null;
    const hasMore = visibleCount < rows.length;

    // Callback ref on the viewer's own row: (re)attach an observer that tracks whether it is on
    // screen, so the sticky "your position" bar shows only while the row is scrolled away.
    const attachMeRow = useCallback((element: HTMLDivElement | null) => {
        meObserverRef.current?.disconnect();
        meElementRef.current = element;

        if (element === null) {
            setMeVisible(false);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setMeVisible(entry.isIntersecting),
            // Trim the bottom so a row hidden behind the sticky bar still counts as off-screen.
            { rootMargin: '0px 0px -96px 0px' },
        );

        observer.observe(element);
        meObserverRef.current = observer;
    }, []);

    // Reveal another page whenever the sentinel scrolls into view.
    const attachSentinel = useCallback(
        (element: HTMLDivElement | null) => {
            if (element === null) {
                return;
            }

            const observer = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        setVisibleCount((count) =>
                            Math.min(count + ROW_STEP, rows.length),
                        );
                    }
                },
                { rootMargin: '300px 0px' },
            );

            observer.observe(element);

            return () => observer.disconnect();
        },
        [rows.length],
    );

    useEffect(() => () => meObserverRef.current?.disconnect(), []);

    const jumpToMe = () => {
        if (myIndex < 0) {
            return;
        }

        // Reveal the viewer's row first (synchronously) if it is past the rendered slice, so it is
        // in the DOM to scroll to.
        if (myIndex >= visibleCount) {
            flushSync(() => setVisibleCount(myIndex + 1));
        }

        meElementRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    };

    return (
        <>
            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                {rows.slice(0, visibleCount).map((row) => (
                    <LeaderboardRow
                        key={row.rank}
                        rootRef={row.is_me ? attachMeRow : undefined}
                        entry={{
                            rank: row.rank,
                            name: row.name,
                            initials: row.initials,
                            avatar: row.avatar,
                            primary: row.primary_value,
                            secondary: secondaryStat(
                                board,
                                row.secondary_value,
                            ),
                            isMe: row.is_me,
                            movement: row.movement,
                            movementDelta: row.movement_delta,
                            prize: showPrizes
                                ? prizeForPlace(pricing, row.rank)
                                : undefined,
                        }}
                    />
                ))}

                {hasMore && (
                    <div
                        ref={attachSentinel}
                        className="flex items-center justify-center gap-2 border-t border-border py-4 text-xs font-medium text-muted-foreground"
                    >
                        <Loader2 className="size-4 animate-spin" />
                        Loading more — {visibleCount} of {rows.length}
                    </div>
                )}
            </div>

            {myRow && !meVisible && (
                <div className="pointer-events-none fixed inset-x-0 bottom-[var(--pool-tab-bar-h)] z-40 flex justify-center px-3 pb-3 sm:pb-6">
                    <div className="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-2xl border border-border bg-card/95 px-4 py-2.5 shadow-[var(--sh-lg)] backdrop-blur">
                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-pitch-deep font-display text-sm font-semibold text-white tabular-nums">
                            {myRow.rank}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-display text-sm font-semibold">
                                Your position
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {formatValue(myRow.primary_value)}{' '}
                                {board.primary_stat_label.toLowerCase()}
                            </p>
                        </div>
                        {myRow.movement != null && (
                            <MovementArrow
                                movement={myRow.movement}
                                delta={myRow.movement_delta}
                                size="md"
                            />
                        )}
                        <Button
                            type="button"
                            size="sm"
                            variant="solid"
                            onClick={jumpToMe}
                            className="shrink-0"
                        >
                            <LocateFixed className="size-4" />
                            Jump to me
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}
