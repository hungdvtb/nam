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
    $store->writeJson('pa_training/chat_history.json', [
        ['id'=>'old','role'=>'user','content'=>'cũ','createdAt'=>'2026-10-12T01:00:00Z','symbol'=>'TEST','timeframe'=>'H4'],
        ['id'=>'new','role'=>'assistant','content'=>'mới','createdAt'=>'2026-10-12T03:00:00Z','symbol'=>'TEST','timeframe'=>'H4'],
    ]);
    $history = paTrainingChatHistoryAction($store, ['context'=>['symbol'=>'TEST','timeframe'=>'H4']])['messages'];
    checkCaseApi($history[0]['id']==='old' && $history[1]['id']==='new', 'chat history sorted oldest to newest for latest scroll');
    paTrainingWorkspaceState($store, ['state'=>['TEST_H4'=>['symbol'=>'TEST','timeframe'=>'H4','selectedCaseId'=>'case_x','replayIndex'=>321]]]);
    $workspace = paTrainingWorkspaceState($store, [] )['workspace'];
    checkCaseApi($workspace['TEST_H4']['replayIndex']===321 && $workspace['TEST_H4']['selectedCaseId']==='case_x', 'workspace position survives reload');
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
