import { useCallback, useEffect, useRef, useState } from 'react';

export interface SwipeToDismissOptions {
    /** Engage the gesture only when true (the small-screen bottom-sheet view). */
    enabled: boolean;
    /** Imperatively close the sheet, called after the off-screen transition. */
    onClose: () => void;
    /** Pixels past which a release dismisses. Default 140. */
    distanceThreshold?: number;
    /** Fraction of the sheet height that also counts as "far enough". Default 0.35. */
    fractionThreshold?: number;
    /** Downward velocity (px/ms) at release that dismisses regardless of distance. Default 0.5. */
    velocityThreshold?: number;
}

export interface SwipeToDismissResult {
    /** Attach to the Radix `*.Content` node (it forwards the ref to the DOM). */
    contentRef: (node: HTMLDivElement | null) => void;
}

function isScrollable(element: HTMLElement): boolean {
    const overflowY = getComputedStyle(element).overflowY;

    return (
        (overflowY === 'auto' || overflowY === 'scroll') &&
        element.scrollHeight > element.clientHeight
    );
}

/** Nearest scrollable element from `start` up to (and including) `boundary`. */
function getScrollableAncestor(
    start: HTMLElement | null,
    boundary: HTMLElement,
): HTMLElement | null {
    let element: HTMLElement | null = start;

    while (element) {
        if (isScrollable(element)) {
            return element;
        }

        if (element === boundary) {
            break;
        }

        element = element.parentElement;
    }

    return null;
}

function findOverlay(node: HTMLElement): HTMLElement | null {
    return (
        node.parentElement?.querySelector<HTMLElement>(
            '[data-slot$="-overlay"]',
        ) ?? null
    );
}

/**
 * Finger-following swipe-down-to-dismiss for a bottom sheet. The Radix content
 * node follows the touch live; releasing past a distance/velocity threshold
 * closes it (driven off-screen by us, then routed through Radix so its
 * focus/scroll-lock lifecycle still runs), otherwise it springs back.
 *
 * Touch-only and React-Compiler-safe: all drag state lives in refs and visual
 * updates write `element.style` directly, so there are no re-renders mid-drag.
 * The drag only starts when the nearest scrollable ancestor is at the top, so
 * it never fights the sheet's own scrolling.
 */
export function useSwipeToDismiss({
    enabled,
    onClose,
    distanceThreshold = 140,
    fractionThreshold = 0.35,
    velocityThreshold = 0.5,
}: SwipeToDismissOptions): SwipeToDismissResult {
    const nodeRef = useRef<HTMLDivElement | null>(null);

    // Mirror props so the stable touch handlers always read fresh values
    // (updated in an effect — refs must not be written during render).
    const onCloseRef = useRef(onClose);
    const enabledRef = useRef(enabled);
    const optsRef = useRef({
        distanceThreshold,
        fractionThreshold,
        velocityThreshold,
    });

    useEffect(() => {
        onCloseRef.current = onClose;
        enabledRef.current = enabled;
        optsRef.current = {
            distanceThreshold,
            fractionThreshold,
            velocityThreshold,
        };
    });

    const drag = useRef({
        active: false,
        decided: false,
        engaged: false,
        startX: 0,
        startY: 0,
        lastY: 0,
        lastT: 0,
        velocity: 0,
        scroller: null as HTMLElement | null,
        height: 0,
    }).current;

    // Build the handlers once (stable across renders); they read everything
    // through refs at event time, so no re-renders happen mid-drag.
    const [handlers] = useState(() => {
        const settle = (node: HTMLDivElement, dy: number): void => {
            const opts = optsRef.current;
            const distance = Math.min(
                opts.distanceThreshold,
                drag.height * opts.fractionThreshold,
            );
            const shouldClose =
                dy > distance || drag.velocity > opts.velocityThreshold;
            const overlay = findOverlay(node);

            if (shouldClose) {
                // Neutralise Radix's slide-out keyframe and drive the sheet
                // off-screen ourselves, then close once it's already gone.
                node.setAttribute('data-dragging-close', '');
                node.style.transition =
                    'transform 260ms cubic-bezier(0.32, 0.72, 0, 1)';
                void node.offsetHeight; // reflow so the transition picks up
                node.style.transform = `translateY(${drag.height}px)`;

                if (overlay) {
                    overlay.style.transition = 'opacity 260ms ease';
                    overlay.style.opacity = '0';
                }

                const done = (): void => {
                    node.removeEventListener('transitionend', done);
                    onCloseRef.current();
                };

                node.addEventListener('transitionend', done);

                return;
            }

            // Spring back, then wipe inline styles so the next open is pristine.
            node.style.transition =
                'transform 220ms cubic-bezier(0.32, 0.72, 0, 1)';
            void node.offsetHeight;
            node.style.transform = 'translateY(0px)';

            if (overlay) {
                overlay.style.transition = 'opacity 220ms ease';
                overlay.style.opacity = '';
            }

            const done = (): void => {
                node.removeEventListener('transitionend', done);
                node.style.transition = '';
                node.style.transform = '';

                if (overlay) {
                    overlay.style.transition = '';
                    overlay.style.opacity = '';
                }
            };

            node.addEventListener('transitionend', done);
        };

        const onTouchStart = (event: TouchEvent): void => {
            if (!enabledRef.current || event.touches.length !== 1) {
                return;
            }

            const node = nodeRef.current;

            if (!node || node.hasAttribute('data-dragging-close')) {
                return;
            }

            const touch = event.touches[0];
            drag.active = true;
            drag.decided = false;
            drag.engaged = false;
            drag.startX = touch.clientX;
            drag.startY = touch.clientY;
            drag.lastY = touch.clientY;
            drag.lastT = event.timeStamp;
            drag.velocity = 0;
            drag.height = node.offsetHeight || window.innerHeight;
            drag.scroller = getScrollableAncestor(
                event.target as HTMLElement | null,
                node,
            );
            node.style.transition = 'none';
        };

        const onTouchMove = (event: TouchEvent): void => {
            if (!drag.active) {
                return;
            }

            const node = nodeRef.current;

            if (!node) {
                return;
            }

            const touch = event.touches[0];
            const dx = touch.clientX - drag.startX;
            const dy = touch.clientY - drag.startY;

            // Decide intent once past a small dead-zone.
            if (!drag.decided) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) {
                    return;
                }

                drag.decided = true;

                const horizontal = Math.abs(dx) > Math.abs(dy);
                const scrolledDown =
                    drag.scroller !== null && drag.scroller.scrollTop > 0;

                // Hand the gesture back to the browser unless it's a clean
                // downward pull from the top of the scroll.
                if (horizontal || dy <= 0 || scrolledDown) {
                    drag.active = false;
                    drag.engaged = false;
                    node.style.transition = '';

                    return;
                }

                drag.engaged = true;
            }

            if (!drag.engaged) {
                return;
            }

            // We own this gesture: stop native scroll / rubber-banding.
            event.preventDefault();

            const dt = event.timeStamp - drag.lastT || 16;
            const vy = (touch.clientY - drag.lastY) / dt;
            drag.velocity = drag.velocity * 0.7 + vy * 0.3;
            drag.lastY = touch.clientY;
            drag.lastT = event.timeStamp;

            // Follow the finger; resist upward drag so it stays anchored.
            const offset = dy >= 0 ? dy : dy * 0.2;
            node.style.transform = `translateY(${offset}px)`;

            const overlay = findOverlay(node);

            if (overlay) {
                const progress = Math.min(1, Math.max(0, dy / drag.height));
                overlay.style.opacity = String(1 - progress * 0.6);
            }
        };

        const finish = (endY: number): void => {
            const node = nodeRef.current;
            const wasEngaged = drag.engaged;
            const startY = drag.startY;
            drag.active = false;
            drag.decided = false;
            drag.engaged = false;

            if (!node) {
                return;
            }

            if (!wasEngaged) {
                node.style.transition = '';

                return;
            }

            settle(node, Math.max(0, endY - startY));
        };

        const onTouchEnd = (event: TouchEvent): void => {
            finish(event.changedTouches[0]?.clientY ?? drag.lastY);
        };

        const onTouchCancel = (): void => {
            finish(drag.lastY);
        };

        const attach = (node: HTMLElement): void => {
            node.removeEventListener('touchstart', onTouchStart);
            node.removeEventListener('touchmove', onTouchMove);
            node.removeEventListener('touchend', onTouchEnd);
            node.removeEventListener('touchcancel', onTouchCancel);
            node.addEventListener('touchstart', onTouchStart, {
                passive: true,
            });
            node.addEventListener('touchmove', onTouchMove, { passive: false });
            node.addEventListener('touchend', onTouchEnd, { passive: true });
            node.addEventListener('touchcancel', onTouchCancel, {
                passive: true,
            });
        };

        const detach = (node: HTMLElement): void => {
            node.removeEventListener('touchstart', onTouchStart);
            node.removeEventListener('touchmove', onTouchMove);
            node.removeEventListener('touchend', onTouchEnd);
            node.removeEventListener('touchcancel', onTouchCancel);
            node.style.transform = '';
            node.style.transition = '';
            node.removeAttribute('data-dragging-close');
        };

        return { attach, detach };
    });

    const contentRef = useCallback(
        (node: HTMLDivElement | null) => {
            const previous = nodeRef.current;

            if (previous && previous !== node) {
                handlers.detach(previous);
            }

            nodeRef.current = node;

            if (node && enabledRef.current) {
                handlers.attach(node);
            }
        },
        [handlers],
    );

    // Re-sync if `enabled` flips while the sheet is mounted (e.g. a resize
    // across the breakpoint). On disable, detach + clear any inline transform.
    useEffect(() => {
        const node = nodeRef.current;

        if (!node) {
            return;
        }

        if (enabled) {
            handlers.attach(node);
        } else {
            handlers.detach(node);
        }
    }, [enabled, handlers]);

    return { contentRef };
}
