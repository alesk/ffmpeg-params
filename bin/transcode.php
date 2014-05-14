#!/usr/bin/env php  
<?php
// Set up environment
error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 'stderr');

require_once __DIR__ . "/utils.php";
require_once __DIR__ . "/ffmpeg-builder.php";

$help =<<<EOF
  $argv[0] [options]

  -h prints this help
  -s prints SQL for vili
  -f process movies with ffmpeg
  -x imports new hashes to database
  -b <file_name> basic presets (FFMPEG_PRESETS_FILE)
  -c <file_name> custom_presets (FFMPEG_CUSTOM_OPTIONS)
  -i <file_name> source file (default = 'source')
  -n <name1,name2,name3> streams to include
  -d dry run (don't transcode)
EOF;



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
    print($sql);
    if(!$conn->query($sql)) {
      print("Error importing {$sql}");
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

  // upload blobs
  if ($exists('u')) {
    array_map("upload_blob", $streamNames);
  }

  // update database
  if ($exists('x')) {
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
                 . "x::" // import new hashes to database
                 . "b::" // basic presets
                 . "c::" // custom presets
                 . "i::" // input file name
                 . "d::" // dry run
                 . "n::" // stream names to include (defaults to all)
                 . "s::"; // print sql for Vili

// Read configuration
$options = getopt($options_specs);

$get_cmd_option = function($key, $default) use($options) { return isset($options[$key]) ? $options[$key] : $default; };

$presets_file = $get_cmd_option('b', getenv('FFMPEG_PRESETS_FILE') ?: 'default-presets.json');
$custom_options = $get_cmd_option('b', getenv('FFMPEG_CUSTOM_OPTIONS'));

$basic_presets = json_decode(file_get_contents($presets_file), true);
$custom_presets = $custom_options ? json_decode(file_get_contents($custom_options), true) : array();

//print_r(extend_all($basic_presets));
//print_r(merge_presets(extend_all($basic_presets), extend_all($custom_presets)));
main(merge_presets(extend_all($basic_presets), extend_all($custom_presets)), $options);


