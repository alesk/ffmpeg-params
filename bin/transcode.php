#!/usr/bin/env php  
<?php
// Set up environment
error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 'stderr');

$help = "
  import-blobs [commands]

  commands:

  -h prints this help
  -s prints SQL for vili
  -f process movies with ffmpeg
  -i imports new hashes to database
  -b <file_name> basic presets
  -c <file_name> custom_presets
  -s <file_name> source file (default = 'source')
  -n <name1,name2,name3> streams to include
  -d dry run (don't transcode)
\n";


$presets = [
    'mpeg1HQVideo', 
    'mpeg1LQVideo', 
    'mpeg1TeaserVideo', 
    'mpeg4HQ', 
    'mpeg4HQVideo', 
    'mpeg4LQ', 
    'mpeg4LQVideo'
    ];

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

// Utils
function get_sha256($fileName) {return hash('sha256', file_get_contents($fileName));}

function not_empty($str) {return preg_replace("/\s+/","") == "";}

function dget($dict, $key, $default) { return isset($dict[$key]) ? $dict[$key] : $default; }

function make_list($mixed) { return is_array($mixed) ? $mixed : array($mixed); }

function python_like_replace($template, $map) {
  $ret = $template;
  foreach($map as $key => $value) {
    $search = "%(" . $key . ")s";
    $ret = str_replace($search, $value, $ret );
  };
  return $ret;
}

function split_line($line) {return trim($line) == "" ? null : preg_split("/\s+/", $line);}

// ffmpeg tree hierarchy utils

/**
 * extends hashmap under $key with hashmaps under keys
 * in extend attributes
 */
function extend($basic, $key) {
  $map_to_extend = $basic[$key];
  $extend_with = array_map(
    function($key) use ($basic) {return extend($basic, $key);},
    make_list(dget($map_to_extend, 'extends', [])));
  array_push($extend_with, $map_to_extend);
  return call_user_func_array("array_merge", $extend_with);
}

function extend_all($basic) {
  $keys = array_keys($basic);
  $extend = function($key) use ($basic) {return extend($basic, $key);};
  return array_combine($keys, array_map($extend, $keys));
}

// Import transcoded hashes to database
function make_update_sql($sourceHash) {
  $updateTemplate = "update videoStreams set %sBlobHash='%s' where blobHash='%s' limit 1";

  return function($preset, $hash) use ($sourceHash, $updateTemplate) {
    return sprintf($updateTemplate, $preset, $hash, $sourceHash);
  };
}

function build_sql($presets) {
  return array_map(make_update_sql(get_sha256('source')), $presets, array_map("get_sha256", $presets));
}

function import_to_database($sqls) {
  $conn = new mysqli($hostname, $username, $password, $database);
  foreach($sqls as $sql) {
    if(!$conn->query($sql)) {
    }
  }
  $conn->close();
}

/**
 * Blob uploading function
 */
function upload_blob($preset) {
  $cmd = "\$MAB/cli-client/celtra-api POST blobls {$preset}";
  exec($cmd);
}



function build_ffmpeg_cmd($source_file, $dest_file, $params) {
  return "ffmpeg -y -i {$source_file} " . ffmpeg_params($params) . " {$dest_file}";
}

function main($presets, $options) {
  global $help, $ffmpegMap;

  $source_file = isset($options['s']) ?: 'source';
  $exists = function($key) use ($options) {return array_key_exists($key, $options);};
  $isStream = function($v) {return dget($v, 'makeStream', false);};
  $streams = array_filter($presets, $isStream);
  $streamNames = isset($options['n']) ? explode(',', $options['n']) : array_keys($streams);

  if ($exists('h')) {
    print_r($help);
  }

  // do ffmpeg transcoding
  if ($exists('f')) {
    foreach($streamNames as $preset) {
      if (!isset($streams[$preset])) {
        print("Undefined stream name \033[35m{$preset}\033[0m\n");
        exit;
      }

      $cmd = build_ffmpeg_cmd($source_file, $preset, $streams[$preset]);

      if ($exists('d')) {
        // dry run
        print($cmd . "\n");
      } else {
        exec($cmd, $output, $return_var);
      }
    }
  }

  // update database
  if ($exists('u')) {
    import_to_database(build_sql($streamNames));
  }

  // print sql statement
  if($exists('s')) {
    print_r(implode(";\n", build_sql($streamNames)));
  }
};


// array_map(null, $a, $b, $c, ...) zips arrays
$options_specs =   "u::" // upload blobs
                 . "h::" // prints help
                 . "f::" // process with ffmpeg
                 . "i::" // import new hashes to database
                 . "b::" // basic presets
                 . "c::" // custom presets
                 . "i::" // input file name
                 . "d::" // dry run
                 . "n::" // stream names to include (defaults to all)
                 . "s::"; // print sql for Vili

// Read configuration
$options = getopt($options_specs);

$get_cmd_option = function($key, $default) use($options) { return isset($options[$key]) ? $options[$key] : $default; };

$presets_file = $get_cmd_option('b', getenv('FFMPEG_PRESETS_FILE'));
$custom_options = $get_cmd_option('b', getenv('FFMPEG_CUSTOM_OPTIONS'));

$basic_presets = json_decode(file_get_contents($presets_file), true);
$custom_presets = $custom_options ? json_decode(file_get_contents($custom_options), true) : array();

main(array_merge(extend_all($basic_presets), extend_all($custom_presets)), $options);


