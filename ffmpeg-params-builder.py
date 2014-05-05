#!/usr/bin/env python

from params import PRESETS
from utils import mklist, dict_merge


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
    ["height", "alignTo", "-vf scale=\"trunc(oh*a/%(alignTo)s)*%(alignTo)s:min(%(height)s\,ih)\"", ""],
    ["videoCodec", "-codec:v %(videoCodec)s", "-vn"],
    ["audioCodec", "-codec:a %(audioCodec)s", "-an"],
    ["videoBitrate", "-b:v %(videoBitrate)s", ""],
    ["audioBitrate", "-b:a %(audioBitrate)s", ""],
    ["fps", "-r %(fps)s", ""],
    ["format", "-f %(format)s", ""],
    ["strict", "-strict %(strict)s", ""],
    ["videoProfile", "-profile:v %(videoProfile)s", ""],
    ["audioProfile", "-profile:a %(audioProfile)s", ""],
    ["preset", "-preset %(preset)s", ""],
    ["pixFormat", "-pix_fmt %(pixFormat)s", ""],
    ["videoMaxBitRate", "-maxrate %(videoMaxBitRate)s", ""],
    ["videoBufferSize", "-bufsize %(videoBufferSize)s", ""],
    ["constantRateFactor", "-crf %(constantRateFactor)s", ""]
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
    return "ffmpeg -y -i %s %s %s" % \
        (source, make_output_params(calculate_preset(preset, options)), preset)

if __name__ == "__main__":
    print make_cmd("x.mp4", "mpeg4HQ")
    print make_cmd("x.mp4", "mpeg4HQ", {
        "videoBitRate": None,
        "videoMaxBitRate": None,
        "videoBufferSize": None,
        "constantRateFactor":28})
