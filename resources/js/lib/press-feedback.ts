/**
 * Global tap-press feedback controller (touch only).
 *
 * Touch devices have no `:hover`, so a tap returns almost no feedback. CSS gives every `.press` /
 * `.press-soft` element a "down then up" bounce, but it cannot be driven by `:active`: the instant a
 * control disables (a processing spinner) or navigates on tap, `:active` is cleared and the bounce is
 * cut short — exactly what happens on the login, join and wizard-"next" buttons.
 *
 * Instead, on a touch `pointerdown` we toggle a `data-pressing` attribute on the nearest pressable
 * ancestor, which fires a one-shot CSS keyframe (see `resources/css/app.css`). A CSS animation runs
 * to completion regardless of `disabled` / `pointer-events` / re-render, so the full bounce always
 * plays as long as the element stays mounted. The attribute is plain DOM (not a React-managed prop),
 * so a re-render that flips `disabled` will not strip it.
 *
 * Mouse pointers are ignored (desktop keeps its hover affordances); a drag past {@link MOVE_CANCEL_PX}
 * is treated as a scroll and cancels the press so a scrolled-over control does not bounce.
 */
const MOVE_CANCEL_PX = 10;
const BOUNCE_MS = 320;

export function initPressFeedback(): void {
    if (typeof document === 'undefined' || typeof window === 'undefined') {
        return;
    }

    let element: HTMLElement | null = null;
    let startX = 0;
    let startY = 0;
    let pointerId = -1;
    let timer = 0;

    const stop = (): void => {
        window.clearTimeout(timer);
        element?.removeAttribute('data-pressing');
        element = null;
    };

    document.addEventListener(
        'pointerdown',
        (event) => {
            // Touch / pen only — never react to a mouse, including on hybrid devices.
            if (event.pointerType === 'mouse') {
                return;
            }

            const target = (
                event.target as Element | null
            )?.closest<HTMLElement>('.press, .press-soft');

            if (!target) {
                return;
            }

            stop();
            element = target;
            startX = event.clientX;
            startY = event.clientY;
            pointerId = event.pointerId;

            // Re-adding after a forced reflow restarts the keyframe on rapid repeat taps.
            element.removeAttribute('data-pressing');
            void element.offsetWidth;
            element.setAttribute('data-pressing', '');
            timer = window.setTimeout(stop, BOUNCE_MS);
        },
        { passive: true, capture: true },
    );

    document.addEventListener(
        'pointermove',
        (event) => {
            if (!element || event.pointerId !== pointerId) {
                return;
            }

            if (
                Math.abs(event.clientX - startX) > MOVE_CANCEL_PX ||
                Math.abs(event.clientY - startY) > MOVE_CANCEL_PX
            ) {
                // A scroll/drag, not a tap.
                stop();
            }
        },
        { passive: true },
    );

    document.addEventListener(
        'pointercancel',
        (event) => {
            if (element && event.pointerId === pointerId) {
                stop();
            }
        },
        { passive: true },
    );
}
