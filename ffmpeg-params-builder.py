#!/usr/bin/env python

import re
from sys import argv
from utils import mklist, dict_merge, load_json

PRESETS = load_json('default-presets.json')


def calculate_preset(key, opts = {}):
    m = PRESETS.get(key)
    if m:
        extends = mklist(m.get('extends', []))
        extends_dicts = map(calculate_preset, extends) + [m, opts]
        return dict_merge({}, *extends_dicts)
    else:
        return {}


def calcualte_streams():
    presets = [k for k,v in PRESETS.items() if v.get('makeStream')]
    return dict(zip(presets, map(deep_merge, presets)))

"""Each member of list consists of required parameters and template string
to which parameters are passed if all are different of None. else_template
string is used otherwise"""

PARAMS = [
    ["format", "-f %(format)s", ""],
    ["height", "alignTo", "-vf scale=\"trunc(oh*a/%(alignTo)s)*%(alignTo)s:min(%(height)s\,ih)\"", ""],
    ["videoCodec", "-codec:v %(videoCodec)s", "-vn"],
    ["fps", "-r %(fps)s", ""],
    ["constantRateFactor", "-crf %(constantRateFactor)s", ""],
    ["videoBitRate", "-b:v %(videoBitRate)s", ""],
    ["videoMaxBitRate", "-maxrate %(videoMaxBitRate)s", ""],
    ["videoBufferSize", "-bufsize %(videoBufferSize)s", ""],
    ["videoProfile", "-profile:v %(videoProfile)s", ""],
    ["audioCodec", "-codec:a %(audioCodec)s", "-an"],
    ["audioBitRate", "-b:a %(audioBitRate)s", ""],
    ["audioProfile", "-profile:a %(audioProfile)s", ""],
    ["pixFormat", "-pix_fmt %(pixFormat)s", ""],
    ["preset", "-preset %(preset)s", ""],
    ["strict", "-strict %(strict)s", ""],
    ["pass", "-pass %(pass)s", ""],
    ["report", "-report", ""],
    ["threads", "-threads %(threads)s", ""]
    ]

def all_not_none(dct):
    print dct
    return len(filter(lambda x: x, dct.values)) == len(dct)

def make_ffmpeg_params(preset):
    for param in PARAMS:
        keys = param[:-2]
        template = param[-2]
        else_template = param[-1]
        values = [ preset.get(k, None) for k in keys]
        all_specified = not filter(lambda x: x is None, values)
        yield (template if all_specified else else_template) % dict(zip(keys, values))

def make_output_params(preset):
    return " ".join([p for p in make_ffmpeg_params(preset)])

def make_cmd(source, preset, options = {}):
    cmd = "ffmpeg -y -i %s %s %s" % \
        (source, make_output_params(calculate_preset(preset, options)), preset)
    return re.sub(r'\s+', ' ', cmd)

def build_bash(options = {}):
    streamKeys = [ k for k, v in PRESETS.items() if v.get('makeStream')]
    ffmpegs = map(lambda streamKey: make_cmd("$1", streamKey, options.get(streamKey)), streamKeys)
    results = ["#!/bin/sh"] + ffmpegs + [""]
    print "\n".join(results)

if __name__ == "__main__":
    build_bash(load_json(argv[1]) if len(argv) > 1 else {})
