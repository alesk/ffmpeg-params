<?php

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

