/**
 * Per-game "kit" accents. Games played over the *same* tournament are near-identical (same name,
 * dates, sport, counts) and otherwise read as duplicates, so each is given a distinct accent —
 * colour, glow and rail texture — keyed on its position among the tournament's games. The order
 * starts pitch (the house green) so a tournament's first game keeps the brand look.
 *
 * Pitch & gold reuse the existing house helpers; teal & violet add the two extra kits (see the
 * `.kit-rail-*` / `.bg-*-gradient` / `.shadow-glow-*` utilities in `resources/css/app.css`).
 */
export interface GameAccent {
    key: 'pitch' | 'teal' | 'gold' | 'violet';
    /** Textured gradient fill for the ticket's source rail. */
    railClass: string;
    /** Gradient fill for the "Enter game" button. */
    buttonClass: string;
    /** Coloured glow, applied to the button. */
    glowClass: string;
    /** Subtle accent-tinted border for the ticket, replacing the neutral card border. */
    ringClass: string;
    /** Text colour to sit on the gradient (white, except gold which needs ink for contrast). */
    textClass: string;
}

const ACCENTS: readonly GameAccent[] = [
    {
        key: 'pitch',
        railClass: 'kit-rail-pitch',
        buttonClass: 'bg-brand-gradient',
        glowClass: 'shadow-glow',
        ringClass: 'border-pitch/30',
        textClass: 'text-white',
    },
    {
        key: 'teal',
        railClass: 'kit-rail-teal',
        buttonClass: 'bg-teal-gradient',
        glowClass: 'shadow-glow-teal',
        ringClass: 'border-[#2bb3c9]/35',
        textClass: 'text-white',
    },
    {
        key: 'gold',
        railClass: 'kit-rail-gold',
        buttonClass: 'bg-gold-gradient',
        glowClass: 'shadow-glow-accent',
        ringClass: 'border-amber/35',
        textClass: 'text-[#3a2600]',
    },
    {
        key: 'violet',
        railClass: 'kit-rail-violet',
        buttonClass: 'bg-violet-gradient',
        glowClass: 'shadow-glow-violet',
        ringClass: 'border-[#7c6cf0]/35',
        textClass: 'text-white',
    },
];

/** The accent for a game at the given 0-based position among its tournament's games. */
export function gameAccent(index: number): GameAccent {
    return ACCENTS[
        ((index % ACCENTS.length) + ACCENTS.length) % ACCENTS.length
    ];
}

/**
 * A compact, uppercase emblem for a game's source. Multi-word sources become their initials
 * ("Brothers Association" → "BA"); a single token is trimmed to its first three alphanumerics
 * ("FF&A" → "FFA"). Falls back to "?" for an empty source.
 */
export function sourceMonogram(source: string): string {
    const tokens = source
        .replace(/[^a-z0-9& ]/gi, '')
        .split(/\s+/)
        .filter(Boolean);

    const letters =
        tokens.length > 1
            ? tokens.map((token) => token[0]).join('')
            : (tokens[0] ?? '').replace(/[^a-z0-9]/gi, '');

    return letters.slice(0, 3).toUpperCase() || '?';
}
