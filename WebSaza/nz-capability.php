<?php
header('Content-Type: application/json; charset=utf-8');
$disabled=array_filter(array_map('trim',explode(',',(string)ini_get('disable_functions'))));
$canShell=function_exists('shell_exec')&&!in_array('shell_exec',$disabled,true);
$find=function(string $name)use($canShell){foreach(['/usr/bin/'.$name,'/usr/local/bin/'.$name,'/opt/local/bin/'.$name] as $p){if(is_file($p)&&is_executable($p))return true;}if(!$canShell)return false;$x=trim((string)@shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));return $x!=='';};
echo json_encode([
 'gd'=>extension_loaded('gd'),
 'imagewebp'=>function_exists('imagewebp'),
 'ffmpeg'=>$find('ffmpeg'),
 'ffprobe'=>$find('ffprobe'),
 'upload_max_filesize'=>ini_get('upload_max_filesize'),
 'post_max_size'=>ini_get('post_max_size'),
 'max_execution_time'=>ini_get('max_execution_time')
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
