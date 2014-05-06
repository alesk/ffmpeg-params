#!/usr/bin/env python

from sys import argv
from os.path import basename, splitext, join
import os
import subprocess
import urllib2
from patterns import FFMPEG_STAT_PATTERNS
from itertools import product
from random import sample
import operator
import re
import csv
import json

BITRATE_SET = range(300, 1200, 33)
FPS_SET = [8, 12, 16, 20, 24, 30]
HEIGHT_SET= [100, 220, 480, 630]


def movie_time_to_sec(movie_time):
    """
    >>> movie_time_to_sec("01:12:35.2")
    4355.2
    """
    return sum(map(operator.mul, map(float, movie_time.split(":")), [3600, 60, 1]))

VIDEO_STREAM_KEYS=['name', 'size', 'blobHash']
def extract_video_stream(filename):
    with open(filename, 'r') as fl:
        video_streams = (stream for stream in json.load(fl)['files'] if stream.get('type') == 'video')
        for video_stream in video_streams:
            yield dict((key, video_stream[key]) for key in VIDEO_STREAM_KEYS)


def extract_video_streams(path):
    for root, _, files in os.walk(path):
        for filename in files:
            creative_id = filename.replace(".json","")
            for blobhash in extract_video_stream(join(root, filename)):
                yield {
                    "creative_id": creative_id,
                    "blob_hash": blobhash['blobHash'],
                    "size": blobhash['size'],
                    "extension": splitext(blobhash['name'])[1].replace('.','')
                    }

def process(source, output_dir):

    name = splitext(basename(source))[0]
    ret = []

    for bitrate, fps, height in product(BITRATE_SET, FPS_SET, HEIGHT_SET):
        params = {
            'input_bitrate': bitrate,
            'input_fps': fps,
            'source': str(source),
            'height': height,
            'aspect': 1270 / 720,
            'destination': "%s/%s-%s-%s.mp4" %(output_dir, name, fps, bitrate)}

        command = "ffmpeg -f mp4 -r 24  -i %(source)s -vf scale='trunc(oh*a/2)*2:min(%(height)s\,ih)' -y -b:v %(input_bitrate)sk -r %(input_fps)s %(destination)s" % params

        process = subprocess.Popen(command.split(), stderr=subprocess.PIPE)
        parsed  = parse_output(process.communicate()[1])
        ret.append(dict(params.items() + parsed.items() + {"time": movie_time_to_sec(parsed['time'])}.items()))
    return ret



def parse_output(output):
    ret = dict()
    for line in output.split('\n'):
        for pattern in FFMPEG_STAT_PATTERNS:
            match = pattern.search(line)
            if match:
                ret = dict(ret.items() + match.groupdict().items())
    return ret

def write_dicts_to_csv(filename, results):
    with open(filename, 'wb') as f:
        w = csv.DictWriter(f, results[0].keys())
        w.writeheader()
        w.writerows(results)

def process_video(source, output_dir):
    write_dicts_to_csv("results.csv", process(source, output_dir))

def get_videos_from_creatives(path):
    write_dicts_to_csv("creatives.csv", list(extract_video_streams(path)))

VIDEO_URL = "http://cache.celtra.com/api/blobs/%s/"
def sample_videos(sample_size):
    """
    Downloads randomly selected `sample_size` subset of videos potentially limited
    by max_size file size.
    """

    with open('creatives.csv') as fl:
        choosen = sample([v for v in csv.DictReader(fl)], sample_size)
        for blob in choosen:
            filename = join('originals', "%(blob_hash)s.%(extension)s" % blob)
            print "Downloading to %s" % filename
            with open(filename, "wb") as fl:
                response = urllib2.urlopen(VIDEO_URL % blob['blob_hash'])
                fl.write(response.read())

def ffprobe(filename):
    command = "ffprobe -v quiet -print_format json -show_format -show_streams %s" % filename
    process = subprocess.Popen(command.split(), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    return json.loads(process.communicate()[0])

def extract_data(ffprobe_output):
    try:
        format = ffprobe_output['format']
        video = [stream for stream in ffprobe_output['streams'] if stream.get('codec_type') == 'video'][0]
        return {
            'bit_rate': video.get('bit_rate'),
            'display_aspect_ratio': video.get('display_aspect_ratio'),
            'duration': video.get('duration'),
            'width': video.get('width'),
            'height': video.get('height'),
            'codec_name': video.get('codec_name'),
            'pix_fmt': video.get('pix_fmt'),
            'nb_frames': video.get('nb_frames'),
            'avg_frame_rate': video.get('avg_frame_rate'),
            'format_duration': format['duration'],
            'format_size': format['size'],
            'format_bit_rate': format['bit_rate'],
            'format_streams': len(ffprobe_output['streams']),
            'filename': format['filename']
        }
    except:
        return None

def extract_originals_data():
    for root, _, files in os.walk("originals"):
        write_dicts_to_csv(
            'originals.csv',
            filter(lambda x: x is not None,
                   [extract_data(ffprobe(join(root, filename))) for filename in files if filename[0] != "."]))

if __name__ == '__main__':

    #get_videos_from_creatives(argv[1])
    #sample_videos(50)
    extract_originals_data()
    #process_video(argv[1], argv[2])
