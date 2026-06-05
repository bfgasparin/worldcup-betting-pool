import type { SVGAttributes } from 'react';

/**
 * Brothers Bets brand mark — a "BB" monogram (two B's for the two brothers) with a
 * small gold "tie-break" dot. The letters use `currentColor` so the glyph adapts to
 * every context (white inside the green app badge, ink/white via `text-*`), while the
 * dot stays the brand gold in both themes.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect
                x="4"
                y="3.8"
                width="2.6"
                height="16.4"
                rx="1.3"
                fill="currentColor"
            />
            <path
                d="M5.3 4.4a3.3 3.3 0 0 1 0 6.7"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.9"
                strokeLinecap="round"
            />
            <path
                d="M5.3 11a3.6 3.6 0 0 1 0 7.2"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.9"
                strokeLinecap="round"
            />
            <rect
                x="12.9"
                y="3.8"
                width="2.6"
                height="16.4"
                rx="1.3"
                fill="currentColor"
            />
            <path
                d="M14.2 4.4a3.3 3.3 0 0 1 0 6.7"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.9"
                strokeLinecap="round"
            />
            <path
                d="M14.2 11a3.6 3.6 0 0 1 0 7.2"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.9"
                strokeLinecap="round"
            />
            <circle cx="20.6" cy="18.4" r="1.55" fill="#ffc23c" />
        </svg>
    );
}
