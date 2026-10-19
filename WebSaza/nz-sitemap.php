<?php
declare(strict_types=1);
require_once __DIR__.'/nz-community.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
$db=nzc_db_read();
$urls=[['loc'=>'https://websaza.ir/nz','lastmod'=>date('c'),'priority'=>'1.0']];
$seen=[];
foreach($db['posts']??[] as$p){
    if(($p['status']??'')!=='published'||empty($p['id']))continue;
    $id=(string)$p['id'];$urls[]=['loc'=>'https://websaza.ir/nz?id='.rawurlencode($id),'lastmod'=>(string)($p['updated_at']??$p['created_at']??date('c')),'priority'=>'0.8'];
    foreach([['category',(string)($p['category']??'')],['city',(string)($p['city']??'')]] as[$k,$v]){
        if($v==='')continue;$key=$k.'|'.$v;if(isset($seen[$key]))continue;$seen[$key]=true;
        $urls[]=['loc'=>'https://websaza.ir/nz?page=search&'.rawurlencode($k).'='.rawurlencode($v),'lastmod'=>date('c'),'priority'=>'0.5'];
    }
}
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as$u){echo '<url><loc>'.htmlspecialchars($u['loc'],ENT_XML1,'UTF-8').'</loc><lastmod>'.htmlspecialchars($u['lastmod'],ENT_XML1,'UTF-8').'</lastmod><priority>'.$u['priority'].'</priority></url>';}
echo '</urlset>';
