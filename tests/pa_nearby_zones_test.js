const assert = require('node:assert/strict');
const { nearest } = require('../assets/pa-zones.js');
const bars = Array.from({length:100},(_,i)=>{
    const close=100+Math.sin(i*.6)*5+Math.sin(i*.19)*2;
    return {open:close-.2,close,high:close+.5,low:close-.5};
});
for (const direction of ['long','short']) {
    const result=nearest(bars,65,direction);
    assert.deepEqual(result,nearest(bars.slice(0,66),65,direction),'future candles must not affect replay zones');
    assert.ok(result.entries.length<=2 && result.targets.length<=2);
    assert.ok(result.entries.length+result.targets.length>0,'fixture must produce real zones');
    if (direction === 'short') assert.equal(result.targets.length, 2, 'show both targets when two valid zones exist');
    for (const z of [...result.entries,...result.targets]) {
        assert.ok(z.confirmedIndex<=65 && z.high>z.low);
        for(let j=z.originIndex+3;j<=65;j++) assert.ok(z.type==='demand'?bars[j].close>=z.low:bars[j].close<=z.high,'broken zones excluded');
    }
    result.targets.forEach(z=>assert.ok(direction==='long'?z.low>result.price:z.high<result.price));
    for(let i=1;i<result.targets.length;i++) assert.ok(result.targets[i-1].distance<=result.targets[i].distance);
}
assert.deepEqual(nearest([],0).entries,[]);
const rising=Array.from({length:40},(_,i)=>({open:i+10,close:i+11,high:i+12,low:i+9}));
assert.equal(nearest(rising,39).targets.length,0,'no invented targets when no confirmed zones exist');
console.log('pa_nearby_zones_test passed');
