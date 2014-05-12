#!/usr/bin/env python

import re
from optparse import OptionParser
import os
from sys import argv
from utils import mklist, dict_merge, load_json


_options = None

def calculate_preset(key, opts = {}):
    m = get_base_presets().get(key)
    if m:
        extends = mklist(m.get('extends', []))
        extends_dicts = map(calculate_preset, extends) + [m, opts]
        return dict_merge({}, *extends_dicts)
    else:
        return {}


def calcualte_streams():
    presets = [k for k,v in get_base_presets().items() if v.get('makeStream')]
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
    ]

def all_not_none(dct):
    print dct
    return len(filter(lambda x: x, dct.values)) == len(dct)

_presets = None
def get_base_presets():
    global _presets
    if _presets is None:
        _presets = load_json(_options.base_presets)
    return _presets

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

def make_cmd(custom_settings, preset):
    cmd = "ffmpeg -y -i %s %s %s" % \
        (_options.source_video, make_output_params(calculate_preset(preset, custom_settings)), preset)
    return re.sub(r'\s+', ' ', cmd)

def build_bash(custom_settings, source_video = '$1'):
    streamKeys = [ k for k, v in get_base_presets().items() if v.get('makeStream')]
    ffmpegs = map(lambda streamKey: make_cmd(custom_settings.get(streamKey), streamKey), streamKeys)
    results = ["#!/bin/sh"] + ffmpegs + [""]
    print "\n".join(results)

def main():
    global _options
    parser = OptionParser(description = "Create batch file from video transcoding")

    parser.add_option('-p', '--presets',
                        dest='base_presets',
                        default=os.environ.get('FFMPEG_get_base_presets()', 'default-presets.json'),
                        help = 'File with default ffmpeg presets')

    parser.add_option('-s', '--source-video',
                        dest='source_video',
                        default='$1',
                        help='Name of source video in script.')

    _options, args = parser.parse_args();
    build_bash(len(args) > 0 and load_json(args[0]) or {})


if __name__ == "__main__":
    main()
