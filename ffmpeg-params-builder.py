#!/usr/bin/env python

import re
import json
from utils import mklist, dict_merge, load_json

PRESETS = load_json('default-presets.json')


def calculate_preset(key, opts = {}):
    m = PRESETS[key]
    extends = mklist(m.get('extends', []))
    extends_dicts = map(calculate_preset, extends) + [m, opts]
    return dict_merge({}, *extends_dicts)


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

def krista():
    print "#!/bin/sh"
    print make_cmd("$1", "mpeg1TeaserVideo")
    print make_cmd("$1", "mpeg1LQVideo")
    print make_cmd("$1", "mpeg1HQVideo")
    print make_cmd("$1", "mpeg4LQ")
    print make_cmd("$1", "mpeg4LQVideo")
    print make_cmd("$1", "mpeg4HQ")
    print make_cmd("$1", "mpeg4HQVideo")

def krista_hq():
    mp4_hq_settings = {
        "videoBitRate": None,
        "videoMaxBitRate": None,
        "videoBufferSize": None,
        "constantRateFactor":35}

    print "#!/bin/sh"
    print make_cmd("$1", "mpeg1TeaserVideo", {"videoBitRate": "200k"})
    print make_cmd("$1", "mpeg1LQVideo", {"videoBitRate": "300k"})
    print make_cmd("$1", "mpeg1HQVideo", {"videoBitRate": "400k"})
    print make_cmd("$1", "mpeg4LQ", mp4_hq_settings)
    print make_cmd("$1", "mpeg4LQVideo", mp4_hq_settings)
    print make_cmd("$1", "mpeg4HQ", mp4_hq_settings)
    print make_cmd("$1", "mpeg4HQVideo", mp4_hq_settings)

if __name__ == "__main__":
    #krista()
    krista_hq()
