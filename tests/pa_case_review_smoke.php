<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/PaReviewService.php';
function expectCase(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$trade = ['id'=>'case_test','symbol'=>'TEST','direction'=>'long','zoneLow'=>98,'zoneHigh'=>100,'entryPrice'=>101,'initialStop'=>97,'targetPrice'=>110,'netR'=>3];
$memory = ['setup_reviews'=>[['id'=>'review_test','createdAt'=>'v1','snapshot'=>['trade'=>$trade,'session'=>['chatMessages'=>[['content'=>'keep chat']]],'originalTrade'=>$trade],'note'=>'old']], 'lessons'=>[['id'=>'linked','sourceReviewId'=>'review_test','content'=>'old'],['id'=>'independent','content'=>'keep']], 'zone_reviews'=>[]];
$updated = PaReviewService::mutate($memory, 'update', ['id'=>'review_test','expectedUpdatedAt'=>'v1','changes'=>['entryPrice'=>100.5,'targetPrice2'=>114],'note'=>'Entry adjusted']);
$r=$updated['setup_reviews'][0];
expectCase($r['snapshot']['trade']['entryPrice']===100.5, 'entry changes');
expectCase($r['snapshot']['originalTrade']['entryPrice']===101, 'original proposal preserved');
expectCase($r['snapshot']['trade']['netR']===null, 'stale PnL invalidated');
expectCase(PaReviewService::correctedTrade($r['snapshot']['trade'], ['targetPrice2'=>null])['targetPrice2']===null, 'optional TP2 can be cleared');
expectCase($r['snapshot']['session']['chatMessages'][0]['content']==='keep chat', 'chat preserved');
expectCase($updated['lessons'][0]['content']==='Entry adjusted', 'linked lesson updated');
$deleted=PaReviewService::mutate($updated,'delete',['id'=>'review_test']);
expectCase(isset($deleted['setup_reviews'][0]['deletedAt']) && isset($deleted['lessons'][0]['deletedAt']), 'soft deletion also excludes linked lessons');
expectCase(!isset($deleted['lessons'][1]['deletedAt']), 'independent lessons retained');
$restored=PaReviewService::mutate($deleted,'restore',['id'=>'review_test']);
expectCase(!isset($restored['setup_reviews'][0]['deletedAt']) && !isset($restored['lessons'][0]['deletedAt']), 'restoration');
foreach ([['initialStop'=>102],['targetPrice'=>99],['zoneLow'=>105],['targetPrice2'=>108],['entryPrice'=>'not a number']] as $changes) {
    try { PaReviewService::correctedTrade($trade,$changes); throw new RuntimeException('bad edit accepted'); } catch (InvalidArgumentException $e) {}
}
try { PaReviewService::mutate($updated,'update',['id'=>'review_test','expectedUpdatedAt'=>'v1']); throw new RuntimeException('stale edit accepted'); } catch (RuntimeException $e) { expectCase(str_contains($e->getMessage(),'cửa sổ khác'),'stale write rejected'); }
$short=array_merge($trade,['direction'=>'short','initialStop'=>105,'targetPrice'=>95]);
expectCase(PaReviewService::correctedTrade($short,['targetPrice2'=>90])['targetPrice2']===90.0,'short target ordering');
echo "pa_case_review_smoke passed\n";
