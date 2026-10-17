<?php
declare(strict_types=1);
define('AI_TRADE_API_LIBRARY_ONLY', true);
require_once __DIR__ . '/../api.php';
function checkCaseApi(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$temp = sys_get_temp_dir() . '/pa-case-api-' . bin2hex(random_bytes(6));
$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore($temp, $defaults);
try {
    $trade = ['id'=>'test','symbol'=>'TEST','direction'=>'long','zoneLow'=>98,'zoneHigh'=>100,'entryPrice'=>101,'initialStop'=>97,'targetPrice'=>110];
    $payload = ['kind'=>'setup','symbol'=>'TEST','decision'=>'session_note','note'=>'Test only','snapshot'=>['trade'=>$trade,'originalTrade'=>$trade]];
    $saved = paTrainingFeedbackSave($store, $payload)['review'];
    $id = $saved['id'];
    checkCaseApi(count(paTrainingSavedCases($store)['saved_cases'])===1, 'neutral save persisted');
    $updated = paTrainingSavedCaseMutate($store,'update',['id'=>$id,'changes'=>['entryPrice'=>100.5],'note'=>'Adjusted'])['saved_cases'][0];
    $store = new JsonStore($temp, $defaults);
    checkCaseApi(paTrainingSavedCases($store)['saved_cases'][0]['snapshot']['trade']['entryPrice']===100.5, 'edit survives reload');
    $payload['reviewId']=$id; $payload['expectedUpdatedAt']=$updated['updatedAt']; $payload['snapshot']=$updated['snapshot'];
    paTrainingFeedbackSave($store,$payload);
    checkCaseApi(count(paTrainingSavedCases($store)['saved_cases'])===1,'resave updates same case');
    paTrainingSavedCaseMutate($store,'delete',['id'=>$id]);
    checkCaseApi(count(paTrainingMemory($store)['setup_reviews'])===0, 'trash excluded from active memory');
    paTrainingStoreLessons($store,paTrainingMemory($store),[['id'=>'independent','content'=>'New lesson']]);
    checkCaseApi(count(paTrainingSavedCases($store)['saved_cases'])===1,'adding lesson preserves trash');
    paTrainingSavedCaseMutate($store,'restore',['id'=>$id]);
    checkCaseApi(count(paTrainingMemory($store)['setup_reviews'])===1,'restore persists');
    $retrievalLessons = [];
    for ($i = 1; $i <= 14; $i++) {
        $retrievalLessons[] = ['id'=>'lesson_'.$i,'title'=>'Rule '.$i,'content'=>$i === 1 ? 'Wait for candle confirmation before entry.' : 'General review note '.$i.'.','kind'=>'trade_case','symbol'=>'TEST','timeframe'=>'H4','confidence'=>4,'tags'=>['feedback']];
    }
    $retrieved = paTrainingRelevantLessons(['lessons'=>$retrievalLessons], ['symbol'=>'TEST','timeframe'=>'H4'], null, null, 'wait for candle confirmation');
    checkCaseApi(count($retrieved) > 8, 'chat lesson retrieval must use the token budget instead of a fixed item count');
    checkCaseApi($retrieved[0]['content'] === 'Wait for candle confirmation before entry.', 'lesson retrieval must rank matches to the current question first');
    $deduplicated = paTrainingRelevantLessons(['lessons'=>[$retrievalLessons[0], $retrievalLessons[0]]], ['symbol'=>'TEST','timeframe'=>'H4'], null, null, 'candle confirmation');
    checkCaseApi(count($deduplicated) === 1 && $deduplicated[0]['support_count'] === 2, 'repeated identical lessons must be merged and show their support count');
    $longLessons = [];
    for ($i = 1; $i <= 8; $i++) $longLessons[] = ['title'=>'Long '.$i,'content'=>str_repeat('confirmation candle setup ', 35).$i,'symbol'=>'TEST','timeframe'=>'H4'];
    $budgeted = paTrainingRelevantLessons(['lessons'=>$longLessons], ['symbol'=>'TEST','timeframe'=>'H4'], null, null, 'confirmation', 400);
    $budgetTokens = array_sum(array_map(static fn(array $lesson): int => paTrainingLessonEstimatedTokens(json_encode($lesson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), $budgeted));
    checkCaseApi(count($budgeted) < count($longLessons) && $budgetTokens <= 400, 'lesson retrieval must stop at the configured estimated-token budget');
    $store->writeJson('pa_training/chat_history.json', [
        ['id'=>'old','role'=>'user','content'=>'cũ','createdAt'=>'2026-10-12T01:00:00Z','symbol'=>'TEST','timeframe'=>'H4'],
        ['id'=>'new','role'=>'assistant','content'=>'mới','createdAt'=>'2026-10-12T03:00:00Z','symbol'=>'TEST','timeframe'=>'H4'],
    ]);
    $history = paTrainingChatHistoryAction($store, ['context'=>['symbol'=>'TEST','timeframe'=>'H4']])['messages'];
    checkCaseApi($history[0]['id']==='old' && $history[1]['id']==='new', 'chat history sorted oldest to newest for latest scroll');
    $store->writeJson('pa_training/chat_history.json', [
        ['id'=>'zone_a','role'=>'user','content'=>'Vẽ vùng sell','createdAt'=>'2026-10-12T04:00:00Z','symbol'=>'TEST','timeframe'=>'H4','zoneId'=>'zone_a'],
        ['id'=>'zone_b','role'=>'assistant','content'=>'Đã vẽ','createdAt'=>'2026-10-12T05:00:00Z','symbol'=>'TEST','timeframe'=>'H4','zoneId'=>'zone_b'],
        ['id'=>'case_a','role'=>'user','content'=>'Dạy ca này','createdAt'=>'2026-10-12T06:00:00Z','symbol'=>'TEST','timeframe'=>'M15','tradeId'=>'case_a'],
        ['id'=>'other_symbol','role'=>'user','content'=>'Cặp khác','createdAt'=>'2026-10-12T07:00:00Z','symbol'=>'OTHER','timeframe'=>'H4'],
    ]);
    $historyAfterDrawing = paTrainingChatHistoryAction($store, ['context'=>['symbol'=>'TEST','timeframe'=>'H4','selectedZoneId'=>'zone_b']])['messages'];
    checkCaseApi(count($historyAfterDrawing)===3, 'chart conversation must survive a zone change after an AI drawing');
    $historyOnM15 = paTrainingChatHistoryAction($store, ['context'=>['symbol'=>'TEST','timeframe'=>'M15']])['messages'];
    checkCaseApi(count($historyOnM15)===3, 'chart conversation must survive an H4/M15 switch after an AI drawing');
    $historyInCase = paTrainingChatHistoryAction($store, ['context'=>['symbol'=>'TEST','timeframe'=>'M15','selectedTeachingCaseId'=>'case_a']])['messages'];
    checkCaseApi(count($historyInCase)===3, 'selecting a case must not hide earlier chat for the same symbol');
    paTrainingWorkspaceState($store, ['state'=>['TEST_H4'=>[
        'symbol'=>'TEST','timeframe'=>'H4','selectedCaseId'=>'case_x','replayIndex'=>321,
        'skippedCaseIds'=>['case_one','case_two'],
        'chartTimeframe'=>'D1','barsOnScreen'=>240,'rightOffset'=>18,'viewportRightTime'=>1700000000,
        'priceScale'=>1.8,'priceOffset'=>0.0007,
    ]]]);
    $workspace = paTrainingWorkspaceState($store, [] )['workspace'];
    checkCaseApi($workspace['TEST_H4']['replayIndex']===321 && $workspace['TEST_H4']['selectedCaseId']==='case_x', 'workspace position survives reload');
    checkCaseApi($workspace['TEST_H4']['skippedCaseIds']===['case_one','case_two'], 'skipped teaching cases survive reload');
    checkCaseApi($workspace['TEST_H4']['chartTimeframe']==='D1' && $workspace['TEST_H4']['barsOnScreen']===240, 'chart timeframe and zoom survive reload');
    checkCaseApi($workspace['TEST_H4']['rightOffset']===18 && (float)$workspace['TEST_H4']['viewportRightTime']===1700000000.0, 'chart horizontal position survives reload');
    checkCaseApi($workspace['TEST_H4']['priceScale']===1.8 && $workspace['TEST_H4']['priceOffset']===0.0007, 'chart price scale survives reload');
    $marketFixture = $temp . '/market_fixture.json';
    file_put_contents($marketFixture, json_encode([
        'symbol'=>'TEST','timeframe'=>'M15','source'=>'MT5',
        'bars'=>[
            ['time'=>1700000000,'time_iso'=>'2023-11-14T22:13:20+00:00','open'=>1.1,'high'=>1.2,'low'=>1.0,'close'=>1.15,'tick_volume'=>123,'spread'=>2],
            ['time'=>1700000900,'time_iso'=>'2023-11-14T22:28:20+00:00','open'=>1.15,'high'=>1.25,'low'=>1.05,'close'=>1.2,'tick_volume'=>456,'spread'=>3],
        ],
    ], JSON_UNESCAPED_SLASHES));
    $iteratedBars = iterator_to_array(iterateMarketDataBars($marketFixture), false);
    checkCaseApi(count($iteratedBars) === 2 && (float)$iteratedBars[1]['close'] === 1.2, 'market data bars can be decoded incrementally');
    $compactBars = paTrainingReadCompactM15Bars($marketFixture);
    $secondCompactBar = paTrainingM15BarAt($compactBars, 1);
    checkCaseApi(count($compactBars) === 2 && paTrainingM15BarTimestamp($compactBars[1]) === 1700000900, 'compact M15 bars preserve timestamps');
    checkCaseApi((float)$secondCompactBar['open'] === 1.15 && (float)$secondCompactBar['high'] === 1.25 && (float)$secondCompactBar['low'] === 1.05 && (float)$secondCompactBar['close'] === 1.2, 'compact M15 bars preserve OHLC values');
    $marketHandle = fopen($marketFixture, 'rb');
    ob_start();
    streamNormalizedMarketDataJson($marketHandle, false);
    fclose($marketHandle);
    $streamedMarket = json_decode(ob_get_clean(), true);
    checkCaseApi(($streamedMarket['ok'] ?? false) === true && count($streamedMarket['market_data']['bars'] ?? []) === 2, 'market data streams as a valid normalized JSON response');
    checkCaseApi($streamedMarket['market_data']['bars'][1]['index'] === 1 && (float)$streamedMarket['market_data']['bars'][1]['volume'] === 456.0, 'streamed chart bars retain normalized candle fields');
    $manual=paTrainingBuildTeachingCase(['id'=>'manual','symbol'=>'TEST','low'=>98,'high'=>100],[['time'=>1700000000,'close'=>100]],0,[]);
    checkCaseApi($manual===null, 'manual zone cannot become a filled trade without M15 candles');
    $h4Bars = [['time'=>1700000000,'open'=>100,'high'=>101,'low'=>99,'close'=>99.8]];
    $m15Bars = [
        ['time'=>1700000000,'open'=>100.8,'high'=>101,'low'=>99.5,'close'=>99.8],
        ['time'=>1700000900,'open'=>99.9,'high'=>100.1,'low'=>99.4,'close'=>99.6],
        ['time'=>1700001800,'open'=>99.6,'high'=>99.8,'low'=>95.9,'close'=>96.2],
    ];
    $zone = ['id'=>'historical','symbol'=>'TEST','timeframe'=>'H4','source'=>'auto_price_action_scan','type'=>'supply','low'=>99,'high'=>101,'originIndex'=>0,'firstTouchIndex'=>0,'firstTouchTime'=>'1700000000'];
    $filled = paTrainingBuildTeachingCase($zone,$h4Bars,0,$m15Bars);
    checkCaseApi($filled !== null && $filled['entryIndex']===1 && $filled['entryPrice']===99.9, 'entry uses the candle after M15 confirmation');
    checkCaseApi($filled['exitType']==='TP2' && $filled['exitIndex']===2 && $filled['netR']===3.0, 'trade aims for the preferred 3R target and records its realized R');
    checkCaseApi($filled['rr'] >= 2.0 && $filled['rr'] === 3.0 && $filled['targetPrice2'] === null, 'generated setup must meet the 2R floor, prefer 3R, and expose one TP');
    $ambiguous = paTrainingResolvedExit([['time'=>1700000900,'open'=>99.9,'high'=>102,'low'=>97]],0,'short',101.2,97.3,95.0);
    checkCaseApi($ambiguous['type']==='SL' && $ambiguous['sameBarAmbiguous']===true, 'same M15 candle hitting both levels is scored SL');
    $gap = paTrainingResolvedExit([
        ['time'=>1700000900,'open'=>99.9,'high'=>100,'low'=>99.5],
        ['time'=>1700001800,'open'=>102,'high'=>102.2,'low'=>101.5],
    ],0,'short',101.2,97.3,95.0);
    checkCaseApi($gap['type']==='SL' && $gap['price']===102.0, 'stop gap uses the worse opening price');
    $noConfirmation = $m15Bars;
    $noConfirmation[0]['close'] = 100.9;
    $noConfirmation[1] = ['time'=>1700000900,'open'=>102,'high'=>102.1,'low'=>101.2,'close'=>101.5];
    $noConfirmation[2] = ['time'=>1700001800,'open'=>102,'high'=>102.2,'low'=>101.2,'close'=>101.6];
    checkCaseApi(paTrainingBuildTeachingCase($zone,$h4Bars,0,$noConfirmation)===null, 'a zone alone is not a filled trade');
    echo "pa_case_api_smoke passed\n";
} finally {
    // Remove only the unique temporary fixture created above.
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($temp);
}
