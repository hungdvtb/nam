/* Confirmed swing zones, using only bars available at the supplied replay cutoff. */
(function (root) {
    function nearest(bars, cutoff, direction = 'long') {
        const end = Math.min(bars.length - 1, Math.max(0, Math.floor(cutoff)));
        const price = Number(bars[end]?.close);
        if (!Number.isFinite(price) || price <= 0) return { entries: [], targets: [], price: null };
        const start = Math.max(2, end - 1200);
        const zones = [];
        for (let i = start; i + 2 <= end; i++) {
            const b = bars[i];
            const neighbors = [bars[i - 2], bars[i - 1], bars[i + 1], bars[i + 2]];
            const range = Math.max(Number(b.high) - Number(b.low), price * 0.00001);
            const demand = neighbors.every(n => Number(b.low) < Number(n.low));
            const supply = neighbors.every(n => Number(b.high) > Number(n.high));
            for (const type of ['demand', 'supply']) {
                if (type === 'demand' ? !demand : !supply) continue;
                const low = type === 'demand' ? Number(b.low) : Math.max(Number(b.open), Number(b.close), Number(b.high) - range * .35);
                const high = type === 'supply' ? Number(b.high) : Math.min(Number(b.open), Number(b.close), Number(b.low) + range * .35);
                if (!(high > low)) continue;
                let broken = false;
                for (let j = i + 3; j <= end; j++) {
                    if (type === 'demand' ? Number(bars[j].close) < low : Number(bars[j].close) > high) { broken = true; break; }
                }
                if (!broken) zones.push({ type, low, high, originIndex: i, confirmedIndex: i + 2, distance: Math.max(low - price, price - high, 0) });
            }
        }
        const unique = (items, count) => {
            const chosen = [];
            items.sort((a, b) => a.distance - b.distance || b.originIndex - a.originIndex);
            for (const zone of items) {
                if (chosen.some(z => zone.low <= z.high && zone.high >= z.low)) continue;
                chosen.push(zone);
                if (chosen.length === count) break;
            }
            return chosen;
        };
        const buy = unique(zones.filter(z => z.type === 'demand' && z.low <= price), 1);
        const sell = unique(zones.filter(z => z.type === 'supply' && z.high >= price), 1);
        const targets = unique(zones.filter(z => direction === 'short' ? z.type === 'demand' && z.high < price : z.type === 'supply' && z.low > price), 2);
        return { price, cutoff: end, entries: [...buy, ...sell], targets, direction, source: 'confirmed_swings' };
    }
    const api = { nearest };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.PaZones = api;
})(typeof window !== 'undefined' ? window : this);
