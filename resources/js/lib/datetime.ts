/**
 * Format a stored UTC instant as the `YYYY-MM-DDTHH:mm` value a `datetime-local` input expects,
 * expressed in the given IANA timezone. Display-only: the same wall-clock string is sent back to
 * the server, which re-reads it in the chosen venue's timezone — so no client-side timezone math
 * (or parse-back) is needed.
 */
export function toZonedInputValue(iso: string, timeZone: string): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date(iso));

    const get = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? '00';

    // Intl can emit hour "24" at midnight in some runtimes; a datetime-local input wants "00".
    const hour = get('hour') === '24' ? '00' : get('hour');

    return `${get('year')}-${get('month')}-${get('day')}T${hour}:${get('minute')}`;
}
