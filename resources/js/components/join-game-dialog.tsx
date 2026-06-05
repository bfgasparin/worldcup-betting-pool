import { useForm } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useState } from 'react';
import { PrizePanel } from '@/components/prize-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/money';
import games from '@/routes/games';
import type { GameDetail } from '@/types/games';

/**
 * The "Join the pool" call to action. Opens a confirmation that states the buy-in and that
 * payment is settled with the organizer outside the app, shows the prizes, then posts the join.
 * Joining creates the player's entry — the prerequisite for making predictions.
 */
export function JoinGameDialog({ game }: { game: GameDetail }) {
    const [open, setOpen] = useState(false);
    const form = useForm({});
    const buyIn = formatMoney(game.pricing.entry_price, game.pricing.currency);

    const confirm = () => {
        form.post(games.join(game.slug).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Button variant="gold" onClick={() => setOpen(true)}>
                <Coins className="size-4" />
                Join for {buyIn}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="font-display text-xl">
                            Join the pool
                        </DialogTitle>
                        <DialogDescription>
                            The {buyIn} buy-in for {game.source}&apos;s pool is
                            arranged directly with the organizer — there&apos;s
                            no payment inside the app. Join to lock in your spot
                            and start predicting.
                        </DialogDescription>
                    </DialogHeader>

                    <PrizePanel pricing={game.pricing} />

                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setOpen(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="gold"
                            onClick={confirm}
                            disabled={form.processing}
                        >
                            {form.processing ? 'Joining…' : "I'm in"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
