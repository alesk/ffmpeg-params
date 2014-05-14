<?php

// Utils
function get_sha256($fileName) {return hash('sha256', file_get_contents($fileName));}

function not_empty($str) {return preg_replace("/\s+/","") == "";}

function dget($dict, $key, $default) { return isset($dict[$key]) ? $dict[$key] : $default; }

function make_list($mixed) { return is_array($mixed) ? $mixed : array($mixed); }

function embrace($pre, $post) {return function($v) use($pre, $post) {return $pre . $v . $post;};}
function with_prefix($pre) {return embrace($pre, "");}
function with_postfix($post) {return embrace("", $post);}

function python_like_replace($template, $map) {
  $ret = $template;
  foreach($map as $key => $value) {
    $search = "%(" . $key . ")s";
    $ret = str_replace($search, $value, $ret );
  };
  return $ret;
}

function split_line($line) {return trim($line) == "" ? null : preg_split("/\s+/", $line);}

function not_file_or_dir($fileName) {return !(is_dir($fileName) or is_file($fileName));}

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

function merge_presets($basic, $custom) {
  $keys = array_keys($basic) + array_keys($custom);
  $merge_arrays = function($key) use ($basic, $custom) {
    return array_merge(dget($basic, $key, array()), dget($custom, $key, array()));
  };

  return array_combine($keys, array_map($merge_arrays, $keys));
}

function get_streams($presets) {
  return array_filter(
    $presets, function($val) {return dget($val, 'makeStream', false);});
}
function load_presets_json($fileName, $throwWhenFilDoesNotExist = false) {
  if ($throwWhenFilDoesNotExist && !is_file($fileName))
   throw new Exception("File $fileName does not exist.\n");
  return is_file($fileName) ? 
    extend_all(json_decode(file_get_contents($fileName), true)) :
    Array();
}

function preset_filter($presets, $presetNames) {
  return count($presetNames) > 0 ? array_intersect_key($presets, array_combine($presetNames, $presetNames)) : $presets;
}
