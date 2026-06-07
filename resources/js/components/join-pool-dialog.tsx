import { useForm } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useState } from 'react';
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
import pools from '@/routes/pools';
import type { PoolDetail } from '@/types/pools';

/**
 * The "Join the pool" call to action. Opens a confirmation that heroes the buy-in and states that
 * payment is settled with the organizer outside the app, then posts the join. The prize split and
 * fee are deliberately left to the pool page — this step is just "pay this much to lock your spot".
 * Joining creates the player's entry — the prerequisite for making predictions.
 */
export function JoinPoolDialog({
    pool,
    className,
}: {
    pool: PoolDetail;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({});
    const buyIn = formatMoney(pool.pricing.entry_price, pool.pricing.currency);

    const confirm = () => {
        form.post(pools.join(pool.slug).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <Button
                variant="gold"
                onClick={() => setOpen(true)}
                className={className}
            >
                <Coins className="size-4" />
                Join for {buyIn}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="font-display text-xl">
                            Join {pool.source}&apos;s pool
                        </DialogTitle>
                        <DialogDescription>
                            Payment is arranged directly with the organizer —
                            there&apos;s no payment inside the app. Join to lock
                            in your spot and start predicting.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col items-center gap-1 py-2">
                        <span className="font-display text-4xl font-bold text-foreground">
                            {buyIn}
                        </span>
                        <span className="text-[0.65rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            Buy-in
                        </span>
                    </div>

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
