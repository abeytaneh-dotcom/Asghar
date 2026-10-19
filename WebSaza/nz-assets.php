<?php
declare(strict_types=1);

function nzassets_version(string $file): string {
    static $cache=[];
    if(isset($cache[$file]))return $cache[$file];
    $path=__DIR__.'/'.$file;
    return $cache[$file]=is_file($path)?substr(hash_file('sha256',$path),0,12):'0';
}
function nzassets_rewrite(string $html): string {
    $files=[
        'nz-manifest.json','niazestan-icon-180.php','niazestan-icon-192.php','niazestan-icon-512.php',
        'nz-install-direct.js','nz-media-client.js','nz-geocode-proxy.js','nz-submit-wizard.js','nz-location-privacy.js',
        'nz-taxonomy-client.js','nz-spec-lite.js','nz-brand-model.js','nz-image-moderation.js','nz-draft-preview.js','nz-chat-live.js','nz-a11y.js'
    ];
    foreach($files as$file){
        $quoted=preg_quote('/'.$file,'/');
        $html=preg_replace('/'.$quoted.'(?:\?v=[A-Za-z0-9._-]+)?/','/'.$file.'?v='.nzassets_version($file),$html)??$html;
    }
    $sw=nzassets_version('nz-sw.php');
    $html=preg_replace("#/niazestan-sw\\.js(?:\\?v=[A-Za-z0-9._-]+)?#/","/niazestan-sw.js?v={$sw}",$html)??$html;
    return $html;
}
