#!/usr/bin/env php  
<?php
// Set up environment
error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 'stderr');

require_once __DIR__ . "/Utils.php";
require_once __DIR__ . "/FfmpegBuilder.php";
require_once __DIR__ . "/CeltraClient.php";

// Read configuration
$scriptName = basename(array_shift($argv));
$apiUrl      = getenv('CELTRA_API_URL') ?: null;
$cacheUrl    = preg_replace('/^(https:\/\/)(\w+)(\..*)$/', '${1}cache${3}', $apiUrl);
$apiUsername = getenv('CELTRA_API_USERNAME') ?: null;
$apiPassword = getenv('CELTRA_API_PASSWORD') ?: null;
$tempDir     = (getenv('CELTRA_API_TEMP') ?: '/tmp') . "/ffmpeg";

$help =<<<EOF
  $scriptName [options] command [args]

  Commands:

    get-video-streams <creative_id>

    transcode-batch <custom-json> [streamName, ...]

    transcode <dir> [<custom-json>] [streamName, ...]

    replace-sql <dir>

    update-sql <dir> [streamName, ...]

    import-to-database <dir> [streamName, ...]

    upload-blobs <dir> [streamName, ...]

    delete-custom-blobs <dir> [streamName, ...]

EOF;

function get_video_streams($creativeId, $tempDir, $clientApi, $clientCache) {
      $creativeJson = json_decode($clientApi->request('GET', "creatives/$creativeId"), true);
      $videoStreams = array_filter($creativeJson['files'], function($file) {return $file['type'] == 'video';});

      foreach($videoStreams as $videoStream) {
        $blobHash = $videoStream['blobHash'];
        $outputDir = $tempDir . "/$blobHash";
        $outputFile = $outputDir . "/source";
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        file_put_contents($outputFile, $clientCache->request('GET', "blobs/$blobHash"));
        print("Movie stream downloaded to:\033[35m$outputFile\033[0m\n");
      }
}

function transcode_batch($presets) {
      $ret = [];
      foreach($presets as $presetName => $preset) {
        $ret []= build_ffmpeg_cmd("$1", $presetName, $preset);
      }
      return "#!/bin/bash\n\n" . implode("\n", $ret);
}

function transcode($outputDir, $sourceFile, $presets) {
      foreach($presets as $presetName => $preset) {
        $destFile = $outputDir . "/$presetName";
        $cmd = build_ffmpeg_cmd($sourceFile, $destFile, $preset);
        print("Executing ffmpeg with:\n    \033[36m$cmd\033[0m\n\n");
        exec($cmd, $output, $retVal);
        if ($retVal != 0) {
          die("Error processing $presetName \n");
        }
      }
}

function build_replace_sql($sourceHash, $names, $hashes, $versions) {
  $fieldNames = array_merge(['blobHash'],
    array_map(with_postfix('BlobHash'), $names),
    array_map(with_postfix('AlgoVersion'), $names));

  $fieldValues = array_map(embrace("'", "'"), 
    array_merge([$sourceHash], $hashes, $versions));

  return sprintf("INSERT INTO videoStreams (%s) VALUES (%s)", 
    implode(", ", $fieldNames), implode(", ", $fieldValues));
}

function replace_sql($outputDir, $sourceFile, $presets) {
      $presetNames  = array_keys($presets);
      $sourceHash = get_sha256($outputDir . "/source");
      $presetHashes = array_map("get_sha256", array_map(with_prefix($outputDir . "/"), $presetNames));

      $algoVersion = function($preset) {return dget($preset, 'algoVersion', 1);};
      $presetVersions = array_map($algoVersion, $presets);

      return build_replace_sql($sourceHash, $presetNames,  $presetHashes, $presetVersions);
}

// Import transcoded hashes to database
function make_update_sql($sourceHash) {
  $updateTemplate = "update videoStreams set %sBlobHash='%s' where blobHash='%s' limit 1";

  return function($preset, $hash) use ($sourceHash, $updateTemplate) {
    return sprintf($updateTemplate, $preset, $hash, $sourceHash);
  };
}

function update_sql($outputDir, $sourceFile, $presets) {
  $sourceHash = get_sha256($sourceFile);
  $presetNames = array_keys($presets);
  $presetHashes = array_map("get_sha256", array_map(with_prefix($outputDir . "/"), $presetNames));
  return array_map(make_update_sql($sourceHash), $presetNames,  $presetHashes);
}

function upload_blobs($outputDir,$sourceHash,  $presetNames, $clientApi) {
  foreach($presetNames as $presetName) {
    print("Uploading blob from \033[35m$presetName\033[0m\n");
    $blobPath = $outputDir . "/" . $presetName;
    $clientApi->request('PUT', "/videoStream/$sourceHash/$presetName", file_get_contents($blobPath));
  }
}

function delete_custom_blobs($sourceHash,  $presetNames, $clientApi) {
  foreach($presetNames as $presetName) {
    print("Deleting custom blobs of $sourceHash for preset \033[35m$presetName\033[0m\n");
    $clientApi->request('DELETE', "/videoStream/$sourceHash/$presetName");
  }
}


function main2($command, $arguments, $options) {
  global $apiUrl, $cacheUrl, $apiUsername, $apiPassword, $tempDir, $presetsFile;

  $clientApi   = new CeltraClient($apiUsername, $apiPassword, $apiUrl);
  $clientCache = new CeltraClient(null, null, $cacheUrl);
  $presetsFile = dget($options, 'p', dget($options, 'presets', getenv('FFMPEG_PRESETS_FILE') ?: 'default-presets.json'));
  $presetFilters = array_filter($arguments, "not_file_or_dir");

  $presets = load_presets_json($presetsFile, true);

  switch ($command) {
    case 'get-video-streams':
      $creativeId = $arguments[0];
      get_video_streams($creativeId, $tempDir, $clientApi, $clientCache);
      break;

    case 'transcode-batch':
      $outputDir = $arguments[0];
      $sourceFile = $outputDir . "/source";
      $withCustomPresets = isset($arguments[1]) ? merge_presets($presets, load_presets_json($arguments[1], true)) : $presets;
      print (transcode_batch(get_streams(preset_filter($withCustomPresets, $presetFilters))));
      break;

    case 'transcode':
      $outputDir = $arguments[0];
      $sourceFile = $outputDir . "/source";
      $withCustomPresets = isset($arguments[1]) ? merge_presets($presets, load_presets_json($arguments[1], true)) : $presets;
      transcode($outputDir, $sourceFile, get_streams(preset_filter($withCustomPresets, $presetFilters)));
      break;

    case 'replace-sql':
      $outputDir = $arguments[0];
      $sourceFile = $outputDir . "/source";
      print_r(replace_sql($outputDir, $sourceFile, get_streams($presets)). ";\n");
      break;

    case 'update-sql':
      $outputDir = $arguments[0];
      $sourceFile = $outputDir . "/source";
      $sqls = update_sql($outputDir, $sourceFile, get_streams($presets));
      print_r(implode(";\n",$sqls). ";\n");
      break;

    case 'upload-blobs':
      $outputDir = $arguments[0];
      $sourceHash = get_sha256($outputDir . "/source");
      upload_blobs($outputDir, $sourceHash,  array_keys(get_streams(preset_filter($presets, $presetFilters))), $clientApi);
      break;

    case 'delete-custom-blobs':
      $outputDir = $arguments[0];
      $sourceHash = get_sha256($outputDir . "/source");
      delete_custom_blobs($sourceHash,  array_keys(get_streams(preset_filter($presets, $presetFilters))), $clientApi);
      break;


    default:
      die("Unknown command $command \n");
  }
}


if (count($argv) < 1) {
  print($help);
} else {
  function is_not_option($val) {return substr($val, 0, 1) != '-';}
  $options = getopt("p::", ['presets::']);
  $arguments = array_filter($argv, "is_not_option");
  $command = array_shift($arguments);
  main2($command, $arguments,  $options);
}
