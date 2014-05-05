PRESETS = {
    "base": {
        "alignTo": 2,
        "preset": "slow",
    },

    "mpeg1": {
        "extends": "base",
        "videoCodec": "mpeg1video",
        "fps": "16",
        "strict": "-1",
        "format": "avi",
        "bitrate": "300k",
        "strict": -1

    },

    "mpeg4":  {
        "extends": "base",
        "videoCodec": "libx264",
        "audioCodec": "libfaac",
        "fps": "24",
        "format": "mp4",
        "videoProfile": "main",
        "videoBitrate": "300k",
        "audioBitrate": "64k",
        "pixFormat": "yuv420p",
        "videoBufferSize": "720k"
    },

    "mpeg1TeaserVideo": {
        "makeStream": True,
        "extends": "mpeg1",
        "height": 50
    },

    "aacAudio": {
        "format": "mp4",
        "videoCodec": None,
        "audioCodec": "libfaac",
        "audioBitrate": "64k"
    },

    "mpeg1LQVideo": {
        "makeStream": True,
        "extends": "mpeg4",
        "height": 480
    },

    "mpeg1HQVideo": {
        "extends": "mpeg1",
        "height": 320
    },

    "mpeg4LQ": {
        "extends": "mpeg4",
        "height": 320,
        "videoMaxBitRate": "180k",
        "videoBufferSize": "1200k"
    },

    "mpeg4LQVideo": {
        "extends": "mpeg4",
        "audioCodec": None,
        "height": 320,
        "videoMaxBitRate": "180k"
    },

    "mpeg4HQ": {
        "extends": "mpeg4",
        "height": 480,
        "videoProfile": "main",
        "videoMaxBitRate": "180k",
        "videoBufferSize": "1200k"
    },

    "mpeg4HQVideo": {
        "extends": "mpeg4",
        "audioCodec": None,
        "height": 480,
        "videoProfile": "main",
        "videoMaxBitRate": "180k"
    }


}
