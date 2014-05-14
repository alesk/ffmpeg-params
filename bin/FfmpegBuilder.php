<?php 

require_once __DIR__ . "/Utils.php";

$ffmpegMap = [
    ["format", "-f %(format)s", ""],
    ["height", "alignTo", "-vf scale=\"trunc(oh*a/%(alignTo)s)*%(alignTo)s:min(%(height)s\,ih)\"", ""],
    ["videoCodec", "-codec:v %(videoCodec)s", "-vn"],
    ["fps", "-r %(fps)s", ""],
    ["constantRateFactor", "-crf %(constantRateFactor)s", ""],
    ["bFrames", "-bf %(bFrames)s", ""],
    ["videoBitRate", "-b:v %(videoBitRate)s", ""],
    ["videoMaxBitRate", "-maxrate %(videoMaxBitRate)s", ""],
    ["videoBufferSize", "-bufsize %(videoBufferSize)s", ""],
    ["videoProfile", "-profile:v %(videoProfile)s", ""],
    ["tune", "-tune %(tune)s", ""],
    ["audioCodec", "-codec:a %(audioCodec)s", "-an"],
    ["audioBitRate", "-b:a %(audioBitRate)s", ""],
    ["audioProfile", "-profile:a %(audioProfile)s", ""],
    ["audioChannels", "-ac %(audioChannels)s", ""],
    ["pixFormat", "-pix_fmt %(pixFormat)s", ""],
    ["preset", "-preset %(preset)s", ""],
    ["strict", "-strict %(strict)s", ""],
    ["stats", "-stats", "-nostats"],
    ["movFlags", "-movflags %(movFlags)s", ""],
    ["pass", "-pass %(pass)s", ""],
    ["report", "-report", ""],
    ["peakSignalToNoiseRatio", "-psnr", ""],
    ["threads", "-threads %(threads)s", ""]
    ];

/**
 * serializes ffmpeg parameters
 **/
function ffmpeg_params($preset) {
  global $ffmpegMap;
  $countSetParameters = function($counter, $key) use($preset) { 
    return array_key_exists($key, $preset) && isset($preset[$key]) ? $counter + 1 : $counter; };

  $ret = [];

  foreach($ffmpegMap as $templateWithDeps) {
    $dependencies = array_slice($templateWithDeps, 0, count($templateWithDeps) -2);
    $template = array_slice($templateWithDeps, -2, 1)[0];
    $elseTemplate = array_slice($templateWithDeps, -1, 1)[0];

    $setKeys = array_reduce($dependencies, $countSetParameters, 0);
    $ret[] = ($setKeys == count($dependencies)) ? python_like_replace($template, $preset) : $elseTemplate;
  }

  return preg_replace("/\s+/"," ", implode(" ", $ret));
}

function build_ffmpeg_cmd($source_file, $dest_file, $params) {
  return "ffmpeg -loglevel info  -y -i {$source_file} " . ffmpeg_params($params) . " -stats {$dest_file}";
}

