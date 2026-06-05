/**
 * Format an amount in the given ISO 4217 currency, e.g. formatMoney(50, 'BRL') -> "R$ 50,00".
 * Uses the pt-BR locale so the Brazilian real renders the way the pools are run (R$, comma cents).
 */
export function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(amount);
}
